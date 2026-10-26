<p>Hello {{ $invoice->customer->name }},</p>
<p>Invoice {{ $invoice->number }} ({{ number_format($invoice->amount_cents / 100, 2) }} EUR) was due on {{ $invoice->due_on->toDateString() }}.</p>
<p>{{ $sender->name }}</p>
