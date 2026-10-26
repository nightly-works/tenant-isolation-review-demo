<?php

namespace Tests\Feature;

use App\Mail\OverdueReminder;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TwoAgencies;
use Tests\TestCase;

/**
 * One test per finding of the review. Each one is an attack: it fails on the app as
 * delivered (tag as-delivered, see scripts/prove-flaws.sh) and passes after the fixes.
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
        $this->assertNotContains('IBAN-REF-S-001', array_column($list, 'reference'));
        $this->assertCount(1, $list);

        $this->actingAs($this->alice)->getJson('/payments/'.$this->south['payment']->id)->assertNotFound();
    }

    // Every model whose table has a tenant_id column must use the tenant trait. Users are
    // the exception: they carry the tenant that the scope reads.
    public function test_1b_every_model_with_a_tenant_id_is_scoped(): void
    {
        $unscoped = [];
        foreach (File::files(app_path('Models')) as $file) {
            $class = 'App\\Models\\'.$file->getFilenameWithoutExtension();
            if ($class === User::class) {
                continue;
            }
            $model = new $class;
            if (Schema::hasColumn($model->getTable(), 'tenant_id') && ! in_array(BelongsToTenant::class, class_uses_recursive($class))) {
                $unscoped[] = $class;
            }
        }

        $this->assertSame([], $unscoped);
    }

    // Finding 2: the queue worker has no signed-in user, so the scope switched itself off.
    public function test_2_overdue_reminders_go_only_to_the_agency_s_own_customers(): void
    {
        Mail::fake();
        config(['queue.default' => 'database']);
        $this->actingAs($this->alice)->postJson('/reminders')->assertStatus(202);

        // The worker is another process: nobody is signed in there.
        $this->app['auth']->forgetGuards();
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        Mail::assertSent(OverdueReminder::class, fn ($mail) => $mail->hasTo('billing@acme.test'));
        Mail::assertNotSent(OverdueReminder::class, fn ($mail) => $mail->hasTo('ap@globex.test'));
    }

    // Finding 3: one cache key for every agency.
    public function test_3_dashboard_figures_are_not_shared_through_the_cache(): void
    {
        config(['cache.default' => 'array']);
        $this->actingAs($this->bruno)->getJson('/dashboard')->assertJson(['invoiced_cents' => 990000]);

        $this->actingAs($this->alice)->getJson('/dashboard')->assertJson(['invoiced_cents' => 50000]);
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

        $this->assertDatabaseMissing('invoices', ['number' => 'N-002']);
        $response->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    }
}
