{{--
    "Bayar Otomatis" kasir lewat DOKU (App\Services\Payment\Doku\DokuPosQrisService). Variabel: $pendingQris
    (DokuPosQrisService::present), $pollAction, $cancelAction.

    Mode pop-up resmi DOKU Checkout = halaman payment.url + "?view=iframe" di dalam iframe (yang dilakukan
    jokul-checkout-1.0.0.js). Di sini iframe-nya ditanam langsung di kartu kasir TANPA memuat script DOKU di halaman admin
    (script itu menimpa window.onclick & menyusun HTML dari URL). Sandbox iframe tanpa allow-top-navigation: halaman
    DOKU tidak bisa memindahkan layar kasir. Kartu hanya hilang kalau lunas (struk tampil), kedaluwarsa, atau kasir
    menekan Batalkan + konfirmasi. Status dicek tiap 3 detik ke server (webhook / API status DOKU), bukan dari iframe.
--}}
@if($pendingQris)
    <div wire:poll.3s="{{ $pollAction }}" wire:key="pos-autopay-{{ $pendingQris['payment_id'] }}" data-pos-autopay="doku"
         style="position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.65); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; padding:1rem;">
        <div x-data="{
                left: '',
                timer: null,
                confirming: false,
                tick() {
                    const ms = new Date(@js($pendingQris['expires_at'])).getTime() - Date.now();
                    if (ms <= 0) { this.left = '00:00'; return; }
                    const s = Math.floor(ms / 1000);
                    this.left = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
                },
                reload() {
                    const frame = this.$refs.frame;
                    if (frame) { frame.src = @js($pendingQris['frame_url']); }
                },
                init() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
                destroy() { clearInterval(this.timer); },
            }"
             style="background:#FFFFFF; border:2px solid #662721; border-radius:20px; width:100%; max-width:440px; max-height:94vh; overflow-y:auto; padding:1rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:0.75rem;">
                <div style="min-width:0;">
                    <div style="font-size:0.6875rem; font-weight:900; color:#662721; text-transform:uppercase; letter-spacing:0.06em;">Bayar Otomatis &bull; {{ $pendingQris['method'] === 'QRIS' ? 'QRIS' : $pendingQris['method_label'] }} &bull; DOKU</div>
                    <div style="font-size:0.75rem; color:#7A5A52; margin-top:0.15rem;">{{ $pendingQris['order_number'] }}</div>
                </div>
                <div style="text-align:right; flex-shrink:0;">
                    <div style="font-size:1.25rem; font-weight:900; color:#4F2F2A; line-height:1.15;">Rp {{ number_format((float) $pendingQris['amount'], 0, ',', '.') }}</div>
                    <div style="font-size:0.75rem; color:#662721;">Berlaku <strong x-text="left" style="font-variant-numeric:tabular-nums;"></strong></div>
                </div>
            </div>

            {{-- Satu-satunya cara menutup kartu tanpa lunas: Batalkan + konfirmasi (dua langkah). --}}
            <div x-show="confirming" x-cloak style="margin-top:0.6rem; padding:0.75rem; border-radius:12px; background:#FEF2F2; border:1px solid #FCA5A5; font-size:0.8125rem; color:#991B1B; text-align:center;">
                Batalkan pembayaran ini? Pesanan dibatalkan. Kalau customer ternyata sudah membayar, transaksi tetap dilanjutkan.
                <div style="display:flex; gap:0.5rem; margin-top:0.6rem;">
                    <button type="button" x-on:click="confirming = false"
                            style="flex:1; padding:0.6rem; border-radius:10px; font-weight:800; font-size:0.8125rem; cursor:pointer; border:1.5px solid #E6DAC0; background:#FFFFFF; color:#662721;">Kembali</button>
                    <button type="button" wire:click="{{ $cancelAction }}" wire:loading.attr="disabled" x-on:click="confirming = false"
                            style="flex:1; padding:0.6rem; border-radius:10px; font-weight:800; font-size:0.8125rem; cursor:pointer; border:none; background:#B91C1C; color:#FFFFFF;">Ya, batalkan</button>
                </div>
            </div>

            <div x-show="! confirming" style="display:flex; gap:0.6rem; margin-top:0.6rem;">
                <button type="button" x-on:click="confirming = true"
                        style="flex:1; padding:0.7rem; border-radius:12px; font-weight:800; font-size:0.8125rem; cursor:pointer; border:1.5px solid #FCA5A5; background:#FFFFFF; color:#B91C1C;">
                    Batalkan Pembayaran
                </button>
            </div>

            {{-- wire:ignore: iframe tidak dimuat ulang oleh render Livewire tiap 3 detik. --}}
            <div wire:ignore style="margin-top:0.75rem;">
                @if($pendingQris['frame_url'])
                    <iframe x-ref="frame" src="{{ $pendingQris['frame_url'] }}" title="Pembayaran DOKU"
                            sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads"
                            allow="clipboard-write" referrerpolicy="strict-origin-when-cross-origin" loading="eager"
                            style="display:block; width:100%; height:640px; border:0; border-radius:12px; background:#FFFFFF;"></iframe>
                @else
                    <div style="padding:1.5rem 1rem; border-radius:12px; border:1.5px dashed #FCA5A5; background:#FEF2F2; text-align:center; font-size:0.8125rem; color:#991B1B;">
                        Halaman pembayaran DOKU tidak valid. Batalkan pembayaran ini lalu buat ulang.
                    </div>
                @endif
            </div>

            @if($pendingQris['frame_url'])
                <div style="margin-top:0.5rem; font-size:0.75rem; color:#7A5A52; text-align:center;">
                    QR tidak tampil?
                    <button type="button" x-on:click="reload()" style="background:none; border:none; padding:0; color:#662721; font-weight:800; text-decoration:underline; cursor:pointer;">Muat ulang</button>
                    @if(! empty($pendingQris['redirect_url']))
                        &bull; <a href="{{ $pendingQris['redirect_url'] }}" target="_blank" rel="noopener noreferrer" style="color:#662721; text-decoration:underline;">buka halaman pembayaran</a>
                    @endif
                </div>
            @endif

            <div style="margin-top:0.5rem; text-align:center; font-size:0.75rem; color:#662721;">
                <span style="display:inline-block; width:8px; height:8px; border-radius:999px; background:#16A34A; margin-right:0.3rem;"></span>Menunggu pembayaran customer — struk keluar otomatis setelah lunas.
            </div>

            @if($pendingQris['sandbox'] ?? false)
                <div style="margin-top:0.5rem; padding:0.45rem 0.7rem; border-radius:10px; background:#FFFBEB; border:1px solid #FCD34D; font-size:0.6875rem; line-height:1.45; color:#92400E;">
                    Mode uji coba DOKU (sandbox): QR / VA hanya bisa dibayar lewat simulator pembayaran DOKU, bukan aplikasi bank / e-wallet asli.
                </div>
            @endif
        </div>
    </div>
@endif
