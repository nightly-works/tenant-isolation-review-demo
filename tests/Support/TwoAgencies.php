<?php

namespace Tests\Support;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;

/** Two agencies on the same instance, each with a user, a customer, an overdue invoice and a payment. */
trait TwoAgencies
{
    protected User $alice; // Northwind, the attacker in most tests

    protected User $bruno; // Southpeak, whose data must stay his

    protected array $south = [];

    protected function seedAgencies(): void
    {
        $north = Tenant::create(['name' => 'Northwind Studio']);
        $southpeak = Tenant::create(['name' => 'Southpeak Agency']);
        $this->alice = User::factory()->create(['tenant_id' => $north->id, 'email' => 'alice@northwind.test']);
        $this->bruno = User::factory()->create(['tenant_id' => $southpeak->id, 'email' => 'bruno@southpeak.test']);

        foreach ([[$north, 'Acme Ltd', 'billing@acme.test', 'N-001', 50000], [$southpeak, 'Globex SA', 'ap@globex.test', 'S-001', 990000]] as [$tenant, $name, $email, $number, $amount]) {
            $customer = (new Customer(['name' => $name, 'email' => $email, 'address' => "1 {$name} Street"]))->forceFill(['tenant_id' => $tenant->id]);
            $customer->save();
            $invoice = (new Invoice(['customer_id' => $customer->id, 'number' => $number, 'amount_cents' => $amount, 'due_on' => today()->subDays(10)]))->forceFill(['tenant_id' => $tenant->id]);
            $invoice->save();
            $payment = (new Payment(['invoice_id' => $invoice->id, 'amount_cents' => 1000, 'method' => 'transfer', 'reference' => "IBAN-REF-{$number}"]))->forceFill(['tenant_id' => $tenant->id]);
            $payment->save();
            if ($tenant->is($southpeak)) {
                $this->south = compact('customer', 'invoice', 'payment');
            }
        }
    }
}
