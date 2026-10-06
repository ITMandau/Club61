<?php

namespace App\Mail;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Invoice lunas untuk customer (booking lapangan online & membership): ringkasan di badan email + PDF invoice terlampir.
 * Data sudah disiapkan App\Services\Mail\OrderInvoiceMailer::invoiceData().
 */
class OrderInvoiceMail extends Mailable
{
    /** @param  array<string, mixed>  $invoice */
    public function __construct(public array $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Invoice {$this->invoice['number']} — Pembayaran Berhasil | Club 61");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice.paid',
            text: 'emails.invoice.paid-text',
            with: ['invoice' => $this->invoice],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => Pdf::loadView('emails.invoice.pdf', ['invoice' => $this->invoice])->setPaper('a5', 'portrait')->output(),
                "Invoice-{$this->invoice['number']}.pdf",
            )->withMime('application/pdf'),
        ];
    }
}
