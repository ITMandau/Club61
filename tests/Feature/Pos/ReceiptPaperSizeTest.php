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

    public function test_print_css_targets_58mm_paper_and_48mm_print_area(): void
    {
        $css = ReceiptPaper::printCss(['#struk']);

        $this->assertStringContainsString('@page { size: 58mm auto; margin: 0; }', $css);
        $this->assertStringContainsString('width: 48mm !important', $css);
        $this->assertStringNotContainsString('78mm', $css);
    }

    public function test_pos_pages_use_the_58mm_receipt_style(): void
    {
        Club61PermissionMatrix::syncAllPermissions('web');
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(BookOfflineCourt::class)->assertSee('size: 58mm auto', false)->assertDontSee('width: 78mm', false);
        Livewire::test(JualMembership::class)->assertSee('size: 58mm auto', false)->assertDontSee('width: 78mm', false);

        // Kasir F&B (komponen Livewire) & salinan admin Buku Transaksi (jendela cetak terpisah).
        $this->assertStringContainsString("@include('pos.partials.receipt-print-style'", file_get_contents(resource_path('views/livewire/pos/fnb-cashier-terminal.blade.php')));
        $this->assertStringContainsString('ReceiptPaper::printCss', file_get_contents(resource_path('views/filament/pages/partials/ledger-invoice-modal.blade.php')));
    }
}
