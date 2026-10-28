<?php

namespace App\Services\Mail;

use App\Models\Setting\InvoiceTemplateSetting;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * PDF invoice lunas (A4) dari Template Invoice — satu sumber untuk lampiran email (OrderInvoiceMail), pratinjau, dan
 * contoh PDF di halaman Keuangan → Template Invoice.
 */
class InvoicePdf
{
    public const VIEW = 'emails.invoice.pdf';

    public const PAPER = 'a4';

    /**
     * @param  array<string, mixed>  $invoice  data OrderInvoiceMailer::invoiceData() (atau sample())
     * @param  string|null  $logo  data URI pengganti (pratinjau logo yang belum disimpan)
     */
    public static function html(array $invoice, ?InvoiceTemplateSetting $template = null, ?string $logo = null): string
    {
        return view(self::VIEW, self::viewData($invoice, $template, $logo))->render();
    }

    /** @param  array<string, mixed>  $invoice */
    public static function output(array $invoice, ?InvoiceTemplateSetting $template = null, ?string $logo = null): string
    {
        return Pdf::loadView(self::VIEW, self::viewData($invoice, $template, $logo))->setPaper(self::PAPER, 'portrait')->output();
    }

    /** @return array<string, mixed> */
    private static function viewData(array $invoice, ?InvoiceTemplateSetting $template, ?string $logo): array
    {
        $template ??= InvoiceTemplateSetting::current();

        return ['invoice' => $invoice, 'template' => $template->resolved(), 'logo' => $logo ?? $template->logoDataUri()];
    }

    /**
     * Invoice contoh untuk pratinjau & "Unduh Contoh PDF" — bentuk data sama persis dengan invoice sungguhan
     * (booking 2 lapangan + diskon + biaya layanan + pajak), supaya semua bagian template terlihat.
     *
     * @return array<string, mixed>
     */
    public static function sample(): array
    {
        return [
            'number' => 'ORD-PAD-CONTOH-0001',
            'type' => 'Booking Lapangan Padel',
            'paid_at' => now('Asia/Jakarta')->locale('id')->translatedFormat('d F Y, H:i').' WIB',
            'reference' => 'CONTOH-REF-7F3A21',
            'customer' => ['name' => 'Nama Pelanggan', 'email' => 'pelanggan@contoh.com', 'phone' => '0812 0000 0000'],
            'lines' => [
                ['name' => 'Sewa Court 1 - Panoramic Indoor (2 jam)', 'quantity' => 1, 'unit_price' => 400000, 'subtotal' => 400000],
                ['name' => 'Sewa Raket Padel', 'quantity' => 2, 'unit_price' => 25000, 'subtotal' => 50000],
            ],
            'bookings' => [
                ['court' => 'Court 1 - Panoramic Indoor', 'date' => now('Asia/Jakarta')->addDays(2)->locale('id')->translatedFormat('l, d F Y'), 'time' => '18:00 – 20:00 WIB', 'code' => 'BK-PAD-CONTOH1'],
            ],
            'membership' => null,
            'subtotal' => 450000,
            'discount' => 45000,
            'voucher_code' => 'CONTOH10',
            'service_charge' => 2500,
            'service_charge_name' => 'Biaya Layanan',
            'tax' => 40500,
            'tax_name' => 'PB1 Pajak Daerah (10%)',
            'grand_total' => 448000,
            'method' => 'QRIS',
            'va_number' => null,
            'company_address' => '',
            'invoice_url' => rtrim((string) config('app.url'), '/').'/invoice',
        ];
    }
}
