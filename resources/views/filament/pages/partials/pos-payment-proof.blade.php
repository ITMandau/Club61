{{--
    Form bukti bayar kasir (dipakai modal Reschedule & Settle di Kelola Pemesanan).
    Param: $methodModel (nama properti metode), $method (nilai metode saat ini), $proofModel (nama properti array bukti).
    Validasi sesungguhnya ada di server (App\Services\Pos\PosPaymentProof) — form ini hanya membantu kasir.
--}}
@php
    $input = 'width:100%; border:1px solid #F87171; border-radius:6px; padding:0.4rem; font-size:0.75rem; background:#FFFFFF;';
    $label = 'display:block; font-size:0.6875rem; font-weight:700; color:#991B1B; margin:0.4rem 0 0.2rem;';
@endphp

<label style="{{ $label }}">Metode Bayar</label>
<select wire:model.live="{{ $methodModel }}" style="{{ $input }}">
    @foreach (\App\Services\Pos\PosPaymentProof::METHODS as $code => $name)
        <option value="{{ $code }}">{{ $name }}</option>
    @endforeach
</select>

@if ($method === 'QRIS')
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
        <div>
            <label style="{{ $label }}">Penyedia QRIS</label>
            <select wire:model="{{ $proofModel }}.qris_provider" style="{{ $input }}">
                @foreach (\App\Services\Pos\PosPaymentProof::QRIS_PROVIDERS as $code => $name)
                    <option value="{{ $code }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="{{ $label }}">Nomor RRN (wajib)</label>
            <input type="text" wire:model="{{ $proofModel }}.qris_rrn" maxlength="32" autocomplete="off" placeholder="Min. 6 karakter dari bukti bayar" style="{{ $input }}">
        </div>
    </div>
    <label style="{{ $label }}">Nama Pengirim (opsional)</label>
    <input type="text" wire:model="{{ $proofModel }}.qris_sender_name" maxlength="100" autocomplete="off" style="{{ $input }}">
@elseif (in_array($method, ['EDC_BCA', 'EDC_MANDIRI'], true))
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
        <div>
            <label style="{{ $label }}">Jenis Kartu</label>
            <select wire:model="{{ $proofModel }}.card_type" style="{{ $input }}">
                <option value="DEBIT">Debit</option>
                <option value="CREDIT">Kredit</option>
            </select>
        </div>
        <div>
            <label style="{{ $label }}">4 Digit Terakhir Kartu</label>
            <input type="text" wire:model="{{ $proofModel }}.card_last_4" maxlength="4" inputmode="numeric" autocomplete="off" style="{{ $input }}">
        </div>
        <div>
            <label style="{{ $label }}">Approval Code</label>
            <input type="text" wire:model="{{ $proofModel }}.approval_code" maxlength="12" autocomplete="off" style="{{ $input }}">
        </div>
        <div>
            <label style="{{ $label }}">Trace Number</label>
            <input type="text" wire:model="{{ $proofModel }}.trace_number" maxlength="12" autocomplete="off" style="{{ $input }}">
        </div>
    </div>
@elseif ($method === 'TRANSFER_BANK')
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
        <div>
            <label style="{{ $label }}">Bank</label>
            <input type="text" wire:model="{{ $proofModel }}.transfer_bank" maxlength="30" placeholder="BCA / Mandiri / ..." autocomplete="off" style="{{ $input }}">
        </div>
        <div>
            <label style="{{ $label }}">No. Referensi Transfer</label>
            <input type="text" wire:model="{{ $proofModel }}.transfer_reference" maxlength="40" autocomplete="off" style="{{ $input }}">
        </div>
    </div>
    <label style="{{ $label }}">Nama Pengirim (opsional)</label>
    <input type="text" wire:model="{{ $proofModel }}.transfer_sender_name" maxlength="100" autocomplete="off" style="{{ $input }}">
@endif

<div style="margin-top:0.4rem; font-size:0.65rem; color:#7F1D1D;">
    Wajib shift Frontdesk Padel aktif. Satu bukti bayar hanya bisa dipakai untuk satu transaksi.
</div>
