<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index()
    {
        return Invoice::with('customer')->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
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
