<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OverdueReminder extends Mailable
{
    public function __construct(public Tenant $sender, public Invoice $invoice)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->sender->name}: invoice {$this->invoice->number} is overdue");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.overdue-reminder');
    }
}
