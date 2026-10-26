<?php

namespace App\Http\Controllers;

use App\Models\Payment;

class PaymentController extends Controller
{
    public function index()
    {
        return Payment::with('invoice')->latest()->get();
    }

    public function show(Payment $payment)
    {
        return $payment->load('invoice');
    }
}
