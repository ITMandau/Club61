<?php

namespace Tests\Feature\Pos;

use Livewire\Component;
use Livewire\Features\SupportScriptsAndAssets\SupportScriptsAndAssets;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kartu "Bayar Otomatis" DOKU SNAP di kasir muncul lewat update Livewire (kasir menekan Bayar, tanpa reload). Library QR
 * harus ikut dimuat di update itu — dulu memakai <script> biasa yang tidak dijalankan Livewire, sehingga kotak QR kosong
 * sampai halaman di-refresh.
 */
class AutoPayModalAssetsTest extends TestCase
{
    private function snapCard(): array
    {
        return [
            'gateway' => 'DOKU', 'payment_id' => 'PAY-TEST-1', 'order_number' => 'ORD-PAD-TEST', 'amount' => 1, 'method' => 'QRIS', 'method_label' => 'QRIS',
            'expires_at' => now()->addMinutes(20)->toIso8601String(), 'frame_url' => null, 'redirect_url' => null, 'sandbox' => false,
            'snap' => ['type' => 'QRIS', 'qr_content' => '00020101021226QRIS-TEST', 'va_number' => null, 'bank' => null, 'amount' => 1],
        ];
    }

    public function test_qr_library_is_sent_when_the_snap_card_appears_through_a_livewire_update(): void
    {
        $card = $this->snapCard();
        $component = Livewire::test(new class extends Component
        {
            public ?array $pendingQris = null;

            public array $card = [];

            public function openCard(): void
            {
                $this->pendingQris = $this->card;
            }

            public function render()
            {
                return <<<'BLADE'
                    <div>
                        @include('pos.partials.auto-pay-modal', ['pendingQris' => $pendingQris, 'pollAction' => 'poll', 'cancelAction' => 'cancel', 'simulateAction' => 'sim'])
                    </div>
                BLADE;
            }
        })->set('card', $card);

        // Halaman kasir dibuka tanpa kartu: library QR belum dikirim.
        $this->assertStringNotContainsString('qrcode.min.js', $this->assetsSentWithLastUpdate($component));

        $component->call('openCard');

        // Respons update yang diterima browser membawa library QR (Livewire memuatnya sebelum menggambar kartu).
        $component->assertSeeHtml('data-doku-snap-pos');
        $this->assertStringContainsString('js/qrcode.min.js', $this->assetsSentWithLastUpdate($component), 'library QR dikirim bersama update yang memunculkan kartu');
    }

    /** Isi "assets" pada respons JSON update Livewire terakhir (yang dibaca browser). */
    private function assetsSentWithLastUpdate($component): string
    {
        $response = \Livewire\invade($component)->lastState->getResponse();
        $assets = $response && str_contains((string) $response->headers->get('Content-Type'), 'json') ? (array) $response->json('assets') : [];

        return implode('', $assets);
    }
}
