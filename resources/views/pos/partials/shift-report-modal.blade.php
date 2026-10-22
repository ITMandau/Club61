{{--
    Laporan shift setelah ditutup / Z-Report (POS Padel & POS F&B) — $reportShiftData dari ClosesPosShift.
    Isi #printable-z-report (pos.partials.z-report-body, sama dengan cetak ulang admin) dicetak ke printer struk 58mm
    lewat club61PrintReceipt (pos.partials.receipt-print-script).
--}}
@use('App\Models\Pos\PosCashierShift')
@if ($showShiftReportModal && $reportShiftData)
    @php
        $report = $reportShiftData;
        $matched = ($report['review_status'] ?? null) === PosCashierShift::REVIEW_MATCHED;
    @endphp
    <div style="position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.65); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; padding:1rem;">
        <div style="background:#FFFFFF; border:1.5px solid #E6DAC0; border-radius:18px; width:100%; max-width:440px; max-height:92vh; display:flex; flex-direction:column; overflow:hidden;">
            <div style="background:#FCF8EE; border-bottom:1.5px solid #E6DAC0; padding:0.8rem 1.1rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size:0.625rem; font-weight:900; text-transform:uppercase; letter-spacing:0.06em; color:{{ $matched ? '#15803D' : '#BE123C' }};">
                        {{ $matched ? 'Semua cocok' : 'Perlu review admin' }}
                    </div>
                    <div style="font-size:1rem; font-weight:900; color:#4F2F2A;">Laporan Penutupan Kasir (Z-Report)</div>
                </div>
                <button type="button" wire:click="closeShiftReportModal" aria-label="Tutup" style="background:none; border:none; font-size:1.25rem; color:#78350F; cursor:pointer; line-height:1;">&times;</button>
            </div>

            <div style="padding:1rem 1.1rem; overflow-y:auto;">
                @include('pos.partials.z-report-body', ['report' => $report, 'reprint' => null])
            </div>

            <div style="background:#FCF8EE; border-top:1px solid #E6DAC0; padding:0.75rem 1.1rem; display:flex; justify-content:flex-end; gap:0.5rem;">
                <button type="button" onclick="club61PrintReceipt('#printable-z-report')" style="padding:0.45rem 0.9rem; font-size:0.8125rem; font-weight:800; border:1.5px solid #E6DAC0; background:#FFFFFF; border-radius:8px; cursor:pointer; color:#4F2F2A;">Cetak Z-Report</button>
                <button type="button" wire:click="closeShiftReportModal" style="padding:0.45rem 1.1rem; font-size:0.8125rem; font-weight:900; background:#662721; color:#FFFFFF; border:none; border-radius:8px; cursor:pointer;">Selesai</button>
            </div>
        </div>
    </div>
@endif
