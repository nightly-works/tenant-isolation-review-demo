<?php

namespace Tests\Feature;

use App\Mail\OverdueReminder;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\TwoAgencies;
use Tests\TestCase;

/** What each agency does every day must keep working after the fixes. */
class NormalUseTest extends TestCase
{
    use RefreshDatabase, TwoAgencies;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAgencies();
    }

    public function test_an_agency_lists_reads_and_issues_its_own_invoices(): void
    {
        $this->actingAs($this->alice)->getJson('/invoices')->assertOk()->assertJsonCount(1)->assertJsonPath('0.number', 'N-001');
        $this->actingAs($this->alice)->getJson('/invoices/'.$this->south['invoice']->id)->assertNotFound();

        $acme = Customer::withoutGlobalScope('tenant')->where('email', 'billing@acme.test')->first();
        $this->actingAs($this->alice)->postJson('/invoices', [
            'customer_id' => $acme->id, 'number' => 'N-002', 'amount_cents' => 2500, 'due_on' => today()->addDays(30)->toDateString(),
        ])->assertCreated()->assertJsonPath('customer.name', 'Acme Ltd');
        $this->assertDatabaseHas('invoices', ['number' => 'N-002', 'tenant_id' => $this->alice->tenant_id]);

        $own = $this->actingAs($this->alice)->getJson('/payments')->assertOk()->json();
        $this->assertSame(['IBAN-REF-N-001'], array_column($own, 'reference'));
    }

    public function test_each_agency_sees_its_own_dashboard_figures(): void
    {
        config(['cache.default' => 'array']);
        $this->actingAs($this->alice)->getJson('/dashboard')->assertExactJson(['invoiced_cents' => 50000, 'outstanding_cents' => 50000, 'overdue_count' => 1]);
        $this->actingAs($this->bruno)->getJson('/dashboard')->assertExactJson(['invoiced_cents' => 990000, 'outstanding_cents' => 990000, 'overdue_count' => 1]);
    }

    public function test_queued_reminders_reach_the_agency_s_overdue_customers_under_its_name(): void
    {
        Mail::fake();
        config(['queue.default' => 'database']);
        $this->actingAs($this->bruno)->postJson('/reminders')->assertStatus(202);
        $this->app['auth']->forgetGuards();
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        Mail::assertSent(OverdueReminder::class, 1);
        Mail::assertSent(OverdueReminder::class, fn ($mail) => $mail->hasTo('ap@globex.test') && $mail->sender->name === 'Southpeak Agency');
    }

    public function test_code_without_a_tenant_sees_nothing_unless_it_says_so(): void
    {
        $this->assertSame(0, Invoice::count());
        $this->assertSame(2, Invoice::withoutGlobalScope('tenant')->count());
        $this->assertSame(1, app(CurrentTenant::class)->run($this->bruno->tenant_id, fn () => Invoice::count()));

        $this->expectException(\LogicException::class);
        Customer::create(['name' => 'Orphan', 'email' => 'orphan@example.test']);
    }
}
