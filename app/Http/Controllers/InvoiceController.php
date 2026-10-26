<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index()
    {
        return Invoice::with('customer')->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // Validation rules query the table directly, without the model's tenant scope.
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', app(CurrentTenant::class)->id())],
            'number' => ['required', 'string', 'max:30'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'due_on' => ['required', 'date'],
        ]);

        return Invoice::create($data)->load('customer');
    }

    public function show(Invoice $invoice)
    {
        return $invoice->load('customer', 'payments');
    }
}
