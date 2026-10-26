<?php

namespace App\Http\Controllers;

use App\Jobs\SendOverdueReminders;
use App\Models\Invoice;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    // The sums scan every invoice: cached for ten minutes, under a key per tenant.
    public function stats(Request $request)
    {
        return Cache::remember("tenant:".app(CurrentTenant::class)->id().":dashboard.stats", 600, fn () => [
            'invoiced_cents' => (int) Invoice::sum('amount_cents'),
            'outstanding_cents' => (int) Invoice::whereNull('paid_at')->sum('amount_cents'),
            'overdue_count' => Invoice::overdue()->count(),
        ]);
    }

    public function sendReminders(Request $request)
    {
        SendOverdueReminders::dispatch($request->user()->tenant);

        return response()->json(['queued' => true], 202);
    }
}
