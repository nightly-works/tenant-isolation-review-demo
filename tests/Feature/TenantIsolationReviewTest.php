<?php

namespace Tests\Feature;

use App\Mail\OverdueReminder;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TwoAgencies;
use Tests\TestCase;

/**
 * One test per finding of the review. Each one is an attack: it fails on the app as
 * delivered (tag as-delivered, see scripts/prove-flaws.sh) and passes after the fixes.
 * Assertions that prove a leak carry the message LEAK; the script counts only those, so a
 * crash or any other failed check on the original proves nothing.
 */
class TenantIsolationReviewTest extends TestCase
{
    use RefreshDatabase, TwoAgencies;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAgencies();
    }

    // Finding 1: Payment was added without the tenant trait.
    public function test_1_payments_of_another_agency_are_not_listed_or_readable(): void
    {
        $list = $this->actingAs($this->alice)->getJson('/payments')->assertOk()->json();
        $this->assertNotContains('IBAN-REF-S-001', array_column($list, 'reference'), 'LEAK: listed');
        $this->assertSame(['IBAN-REF-N-001'], array_column($list, 'reference'));

        $show = $this->actingAs($this->alice)->getJson('/payments/'.$this->south['payment']->id);
        $this->assertSame(404, $show->status(), 'LEAK: readable by id');
    }

    // Every model whose table has a tenant_id column must use the tenant trait. Users are
    // the exception: they carry the tenant that the scope reads.
    public function test_1b_every_model_with_a_tenant_id_is_scoped(): void
    {
        $unscoped = [];
        foreach (File::allFiles(app_path('Models')) as $file) {
            $class = 'App\\Models\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            if (! is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) {
                continue;
            }
            if ($class === User::class) {
                continue;
            }
            $model = new $class;
            if (Schema::hasColumn($model->getTable(), 'tenant_id') && ! in_array(BelongsToTenant::class, class_uses_recursive($class))) {
                $unscoped[] = $class;
            }
        }

        $this->assertSame([], $unscoped, 'LEAK: unscoped models');
    }

    // Finding 2: the queue worker has no signed-in user, so the scope switched itself off.
    public function test_2_overdue_reminders_go_only_to_the_agency_s_own_customers(): void
    {
        Mail::fake();
        config(['queue.default' => 'database']);
        $this->actingAs($this->alice)->postJson('/reminders')->assertStatus(202);

        // The worker is another process: nobody is signed in and no request state is left.
        $this->app['auth']->forgetGuards();
        $this->app->forgetScopedInstances();
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        $toOthers = Mail::sent(OverdueReminder::class, fn ($mail) => $mail->hasTo('ap@globex.test'))->count();
        $this->assertSame(0, $toOthers, "LEAK: another agency's customer emailed");
        Mail::assertSent(OverdueReminder::class, fn ($mail) => $mail->hasTo('billing@acme.test'));
    }

    // Finding 3: one cache key for every agency.
    public function test_3_dashboard_figures_are_not_shared_through_the_cache(): void
    {
        config(['cache.default' => 'array']);
        $this->actingAs($this->bruno)->getJson('/dashboard')->assertOk()->assertJson(['invoiced_cents' => 990000]);

        $mine = $this->actingAs($this->alice)->getJson('/dashboard')->assertOk();
        $this->assertSame(50000, $mine->json('invoiced_cents'), "LEAK: another agency's figures");
    }

    // Finding 4: exists:customers,id checks the whole table, not the agency's customers.
    public function test_4_an_invoice_cannot_be_issued_to_another_agency_s_customer(): void
    {
        $response = $this->actingAs($this->alice)->postJson('/invoices', [
            'customer_id' => $this->south['customer']->id,
            'number' => 'N-002',
            'amount_cents' => 100,
            'due_on' => today()->addDays(30)->toDateString(),
        ]);

        $this->assertSame(0, DB::table('invoices')->where('number', 'N-002')->count(), 'LEAK: invoice issued');
        $response->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    }
}
