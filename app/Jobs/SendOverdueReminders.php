<?php

namespace App\Jobs;

use App\Mail\OverdueReminder;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendOverdueReminders implements ShouldQueue
{
    use Queueable;

    public function __construct(public Tenant $tenant)
    {
    }

    // The worker has no signed-in user: the job runs inside its own tenant.
    public function handle(CurrentTenant $current): void
    {
        $current->run($this->tenant->id, function () {
            Invoice::overdue()->with('customer')->each(function (Invoice $invoice) {
                Mail::to($invoice->customer->email)->send(new OverdueReminder($this->tenant, $invoice));
            });
        });
    }
}
