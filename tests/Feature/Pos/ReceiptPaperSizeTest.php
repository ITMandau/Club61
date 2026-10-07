<?php

namespace Tests\Feature\Pos;

use App\Filament\Pages\BookOfflineCourt;
use App\Filament\Pages\JualMembership;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use App\Support\ReceiptPaper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Semua struk kasir dicetak di printer thermal 58mm — satu sumber ukuran di App\Support\ReceiptPaper. */
class ReceiptPaperSizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_css_targets_48mm_print_area_without_the_invalid_page_size(): void
    {
        $css = ReceiptPaper::printCss(['#struk']);

        // "size: 58mm auto" tidak valid → browser mengabaikannya dan kertas ikut driver (bermeter-meter).
        $this->assertStringNotContainsString('58mm auto', $css);
        $this->assertStringContainsString('@page { margin: 0; }', $css);
        $this->assertStringContainsString('width: 48mm !important', $css);
        $this->assertStringNotContainsString('78mm', $css);
    }

    public function test_pc_prints_the_receipt_as_sharp_text_sized_to_the_receipt(): void
    {
        $script = view('pos.partials.receipt-print-script')->render();

        $this->assertStringContainsString('window.club61PrintReceipt', $script);
        $this->assertStringContainsString("'@page { size: 58mm ' + heightMm + 'mm; margin: 0; }'", $script);
        // Teks (bukan gambar): gambar yang disekala Chrome jadi blur & belang di printer thermal.
        $this->assertStringContainsString('window.club61PrintBrowser(el);', $script);
        $this->assertStringNotContainsString('toDataURL', $script);
        // PC: 28 kolom (huruf lebih besar dari 32 kolom font printer).
        $this->assertSame(28, ReceiptPaper::PC_COLUMNS);
        $this->assertStringContainsString("probe.textContent = 'M'.repeat(28);", $script);
        $this->assertStringContainsString('window.club61ReceiptLayout(one, 28)', $script);
        $this->assertStringContainsString('data-club61-receipt-print', view('pos.partials.receipt-print-style', ['selectors' => ['#struk']])->render());

        // Livewire menyisipkan script-nya sebelum tag penutup body PERTAMA di respons. Tag head/body/html di dalam
        // string JS membuat script ini terpotong (SyntaxError + kode tampil sebagai teks di halaman POS).
        $this->assertDoesNotMatchRegularExpression('#</?\s*(head|body|html)[\s>]#i', $script);
    }

    public function test_android_devices_print_pos_style_text_through_rawbt_without_a_dialog(): void
    {
        $script = view('pos.partials.receipt-print-script')->render();

        // Tablet / HP Android (termasuk aplikasi Flutter WebView): struk → teks ESC/POS → aplikasi RawBT → printer.
        $this->assertStringContainsString("/Android/i.test(navigator.userAgent) ? 'rawbt' : 'browser'", $script);
        $this->assertStringContainsString("window.location.href = 'rawbt:base64,' + btoa(binary);", $script);
        $this->assertStringContainsString("localStorage.getItem('club61_print_mode')", $script);
        // Gaya struk POS: 32 kolom font A, tebal (ESC E) & dobel tinggi (GS !) untuk judul dan TOTAL.
        $this->assertSame(32, ReceiptPaper::COLUMNS);
        $this->assertStringContainsString('const COLS = columns || 32;', $script);
        $this->assertStringContainsString('out.push(ESC, 0x61, align[l.align] || 0, ESC, 0x45, l.bold ? 1 : 0, GS, 0x21, l.big ? 0x01 : 0x00);', $script);
        $this->assertStringContainsString("/^total\\b/i.test(l.left)", $script);
    }

    public function test_pos_page_keeps_the_print_script_intact_after_livewire_injects_its_assets(): void
    {
        Club61PermissionMatrix::syncAllPermissions('web');
        $html = $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/book-offline-court')->assertOk()->getContent();

        preg_match_all('#<script>\s*if \(! window\.club61PrintReceipt\)(.*?)</script>#s', $html, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $body) {
            $this->assertStringContainsString('frame.contentWindow.print();', $body);
            $this->assertStringNotContainsString('<script', $body);
        }
    }

    public function test_every_receipt_print_button_uses_the_receipt_printer(): void
    {
        Club61PermissionMatrix::syncAllPermissions('web');
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(BookOfflineCourt::class)->assertSee('club61PrintReceipt', false)->assertDontSee('width: 78mm', false);
        Livewire::test(JualMembership::class)->assertSee('club61PrintReceipt', false)->assertDontSee('width: 78mm', false);

        $views = [
            'filament/pages/book-offline-court.blade.php' => ["club61PrintReceipt('#printable-pos-receipt')", "club61PrintReceipt('#printable-z-report')"],
            'pos/receipts/membership.blade.php' => ["club61PrintReceipt('#printable-membership-receipt')"],
            'livewire/pos/fnb-cashier-terminal.blade.php' => ["club61PrintReceipt('#fnbpos-receipt')", "@include('pos.partials.receipt-print-style'"],
            'filament/pages/partials/ledger-invoice-modal.blade.php' => ['window.club61PrintReceipt(this.$refs.doc)'],
        ];
        foreach ($views as $view => $needles) {
            $source = file_get_contents(resource_path('views/'.$view));
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $source, $view);
            }
            // Dulu window.print() mencetak seluruh halaman dengan kertas sepanjang setelan driver.
            $this->assertStringNotContainsString('onclick="window.print()"', $source, $view);
        }
    }
}
