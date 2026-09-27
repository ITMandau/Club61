<div class="adm-wrap">
    @if($this->selectedMembership)
        @php
            $m = $this->selectedMembership;
            $habit = $this->memberHabitAnalysis;
            $bookings = $this->memberBookings;
            $checkins = $this->memberCheckins;
            $roster = $this->corporateRoster;
            $padelBal = $m->balances->firstWhere('facility', 'PADEL');
            $gymBal = $m->balances->firstWhere('facility', 'GYM');
            $saunaBal = $m->balances->firstWhere('facility', 'SAUNA');
        @endphp

        <!-- ========================================================
             FULL BLADE PAGE: DETAIL MEMBER, HABIT, JADWAL & ROSTER
             (BUKAN MODAL POPUP, LEGA & FULL SCREEN)
             ======================================================== -->
        
        <!-- Top Action & Navigation Bar -->
        <div class="adm-banner" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <button type="button" 
                        wire:click="closeDetail"
                        style="display: inline-flex; align-items: center; gap: 0.5rem; background: #FAF2DE; border: 1.5px solid #DFC387; color: #7A5818; font-weight: 800; font-size: 0.8125rem; padding: 0.5rem 1rem; border-radius: 12px; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(180,130,20,0.12);"
                        onmouseover="this.style.background='#F5E5BE'" onmouseout="this.style.background='#FAF2DE'">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Kembali ke Daftar Kustomer</span>
                </button>

                <div style="margin-top: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span class="adm-pill adm-pill-gold">
                            {{ $m->owner_type === 'ORGANIZATIONAL' ? 'Akun Sponsor Corporate Pool' : 'Keanggotaan Individual' }}
                        </span>
                        <span class="adm-pill" style="background: {{ $m->status === 'ACTIVE' ? '#EAF7EC' : '#FDEDEC' }}; color: {{ $m->status === 'ACTIVE' ? '#1E7E34' : '#C0392B' }}; border: 1px solid {{ $m->status === 'ACTIVE' ? '#85D497' : '#F5B7B1' }};">
                            STATUS: {{ $m->status }}
                        </span>
                    </div>

                    <div class="adm-banner-title" style="font-size: 1.65rem; margin-top: 0.35rem;">
                        {{ $m->user->name ?? 'Tamu Walk-In' }}
                    </div>

                    <div class="adm-banner-sub" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                        <span style="font-family: var(--font-mono); font-weight: 800; color: #8C6418;">
                            KARTU: {{ $m->membership_code }}
                        </span>
                        <span>&bull;</span>
                        <span style="font-weight: 700; color: #5C410F;">
                            Paket: {{ $m->plan->name ?? '-' }}
                        </span>
                        <span>&bull;</span>
                        <span>
                            Masa Aktif: <strong>{{ $m->start_date ? $m->start_date->format('d M Y') : '-' }}</strong> s/d <strong>{{ $m->end_date ? $m->end_date->format('d M Y') : '-' }}</strong>
                            @if($m->end_date)
                                ({{ $m->end_date->isPast() ? 'Sudah Expired' : 'Sisa ' . now()->diffInDays($m->end_date, false) . ' hari lagi' }})
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 Summary Cards: Profil + 3 Saldo Fasilitas -->
        <div class="adm-metrics-grid">
            <!-- Profil Singkat -->
            <div class="adm-metric-card">
                <div class="adm-metric-label">Profil Kontak Member</div>
                <div style="font-weight: 800; color: #1F170D; font-size: 0.95rem; margin-top: 0.35rem;">
                    {{ $m->user->email ?? '-' }}
                </div>
                <div style="font-size: 0.8125rem; color: #1E7E34; font-weight: 700; margin-top: 0.15rem;">
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $m->user->phone ?? '') }}" target="_blank" style="color: inherit; text-decoration: none;">
                        WA: {{ $m->user->phone ?? '-' }} &rarr;
                    </a>
                </div>
                <div class="adm-metric-foot" style="margin-top: 0.5rem;">
                    <span>Daftar sejak: {{ $m->created_at->format('d M Y') }}</span>
                </div>
            </div>

            <!-- Saldo Padel Court -->
            <div class="adm-metric-card">
                <div class="adm-metric-label">Padel Court Quota</div>
                <div class="adm-metric-val" style="color: #1E7E34;">
                    @if($padelBal && $padelBal->quota_type === 'HOURS')
                        {{ (float) $padelBal->remaining_quota }} <span style="font-size: 0.875rem; font-weight: 600;">/ {{ (float) $padelBal->initial_quota }} Jam</span>
                    @elseif($padelBal && $padelBal->quota_type === 'NONE')
                        Diskon {{ $padelBal->discount_percent }}%
                    @else
                        -
                    @endif
                </div>
                <div class="adm-metric-foot">
                    <span>Prioritas Booking: H-{{ $padelBal->booking_priority_days ?? 0 }} hari</span>
                </div>
            </div>

            <!-- Saldo Fitness & Gym -->
            <div class="adm-metric-card">
                <div class="adm-metric-label">Fitness &amp; Gym Quota</div>
                <div class="adm-metric-val" style="color: #8C6418;">
                    @if($gymBal && $gymBal->initial_quota)
                        {{ (float) $gymBal->remaining_quota }} <span style="font-size: 0.875rem; font-weight: 600;">/ {{ (float) $gymBal->initial_quota }} Sesi</span>
                    @elseif($gymBal)
                        Unlimited <span style="font-size: 0.75rem; font-weight: 600;">Akses</span>
                    @else
                        -
                    @endif
                </div>
                <div class="adm-metric-foot">
                    <span>Turnstile Gate Access Active</span>
                </div>
            </div>

            <!-- Saldo Sauna & Cold Plunge -->
            <div class="adm-metric-card">
                <div class="adm-metric-label">Finnish Sauna &amp; Plunge</div>
                <div class="adm-metric-val" style="color: #5C410F;">
                    @if($saunaBal && $saunaBal->initial_quota)
                        {{ (float) $saunaBal->remaining_quota }} <span style="font-size: 0.875rem; font-weight: 600;">/ {{ (float) $saunaBal->initial_quota }} Sesi</span>
                    @elseif($saunaBal)
                        Unlimited
                    @else
                        -
                    @endif
                </div>
                <div class="adm-metric-foot">
                    <span>Diskon Sesi Tambahan: {{ $saunaBal->discount_percent ?? 0 }}%</span>
                </div>
            </div>
        </div>

        <!-- Detail Sub-Navigation Tabs -->
        <div style="display: flex; gap: 0.5rem; border-bottom: 2px solid #E5D5B3; padding-bottom: 0.5rem; flex-wrap: wrap;">
            <button type="button" 
                    wire:click="selectDetailTab('habit_schedule')"
                    style="padding: 0.6rem 1.25rem; border-radius: 12px; font-size: 0.8125rem; font-weight: 800; cursor: pointer; border: none; transition: all 0.2s; {{ $detailTab === 'habit_schedule' ? 'background: #1F170D; color: #F5E5BE; box-shadow: 0 4px 12px rgba(31,23,13,0.25);' : 'background: rgba(255,255,255,0.7); color: #7A643E; border: 1px solid #DFC387;' }}">
                Habit &amp; Jadwal Main Padel / Check-in
            </button>

            @if($m->owner_type === 'ORGANIZATIONAL')
                <button type="button" 
                        wire:click="selectDetailTab('corporate_roster')"
                        style="padding: 0.6rem 1.25rem; border-radius: 12px; font-size: 0.8125rem; font-weight: 800; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; {{ $detailTab === 'corporate_roster' ? 'background: #1F170D; color: #F5E5BE; box-shadow: 0 4px 12px rgba(31,23,13,0.25);' : 'background: rgba(255,255,255,0.7); color: #7A643E; border: 1px solid #DFC387;' }}">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #9B59B6;"></span>
                    <span>Anggota Tim Sponsor &amp; Jadwal ({{ count($roster) }} Karyawan)</span>
                </button>
            @endif

            <button type="button" 
                    wire:click="selectDetailTab('card_info')"
                    style="padding: 0.6rem 1.25rem; border-radius: 12px; font-size: 0.8125rem; font-weight: 800; cursor: pointer; border: none; transition: all 0.2s; {{ $detailTab === 'card_info' ? 'background: #1F170D; color: #F5E5BE; box-shadow: 0 4px 12px rgba(31,23,13,0.25);' : 'background: rgba(255,255,255,0.7); color: #7A643E; border: 1px solid #DFC387;' }}">
                Kartu VIP Digital &amp; Tagihan Order
            </button>

            <button type="button" 
                    wire:click="selectDetailTab('audit_logs')"
                    style="padding: 0.6rem 1.25rem; border-radius: 12px; font-size: 0.8125rem; font-weight: 800; cursor: pointer; border: none; transition: all 0.2s; {{ $detailTab === 'audit_logs' ? 'background: #1F170D; color: #F5E5BE; box-shadow: 0 4px 12px rgba(31,23,13,0.25);' : 'background: rgba(255,255,255,0.7); color: #7A643E; border: 1px solid #DFC387;' }}">
                Audit Log Mutasi Kuota
            </button>
        </div>

        <!-- ==========================================
             SUB-TAB 1: HABIT & JADWAL MAIN
             ========================================== -->
        @if($detailTab === 'habit_schedule')
            <!-- HABIT SCORECARD BANNER -->
            <div class="adm-card" style="border: 2px solid #DFC387;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border-bottom: 1.5px solid #EEDBB0; padding-bottom: 1rem; margin-bottom: 1rem;">
                    <div>
                        <div class="adm-pill adm-pill-gold">Analisis Kebiasaan &amp; Pola Bermain (Member Habit Tracker)</div>
                        <div style="font-family: var(--font-serif); font-size: 1.35rem; font-weight: 900; color: {{ $habit['habit_color'] }}; margin-top: 0.35rem;">
                            {{ $habit['habit_label'] }}
                        </div>
                    </div>

                    <div style="display: flex; gap: 2rem; font-size: 0.8125rem; flex-wrap: wrap;">
                        <div>
                            <div style="color: #7A643E;">Total Match Tanding:</div>
                            <div style="font-weight: 900; font-size: 1.25rem; color: #1F170D;">{{ $habit['total_matches'] }} Sesi</div>
                        </div>
                        <div>
                            <div style="color: #7A643E;">Kunjungan Gym / Sauna:</div>
                            <div style="font-weight: 900; font-size: 1.25rem; color: #1F170D;">{{ $habit['total_checkins'] }} Check-in</div>
                        </div>
                        <div>
                            <div style="color: #7A643E;">Terakhir Bermain:</div>
                            <div style="font-weight: 800; font-size: 1rem; color: #8C6418;">{{ $habit['last_played'] }}</div>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 1rem; font-size: 0.8125rem;">
                    <div style="background: #FAF5E8; border: 1.5px solid #EEDBB0; border-radius: 14px; padding: 1rem;">
                        <div style="color: #7A643E; font-weight: 800; font-size: 0.75rem; text-transform: uppercase;">Preferensi Bermain:</div>
                        <div style="font-weight: 800; color: #1F170D; margin-top: 0.35rem;">Lapangan Favorit: <span style="color: #8C6418;">{{ $habit['favorite_court'] }}</span></div>
                        <div style="font-weight: 800; color: #1F170D; margin-top: 0.2rem;">Waktu Favorit: <span style="color: #8C6418;">{{ $habit['favorite_time'] }}</span></div>
                    </div>

                    <div style="background: #FFFDF9; border: 1.5px dashed #D4AF37; border-radius: 14px; padding: 1rem;">
                        <div style="color: #8C6418; font-weight: 800; font-size: 0.75rem; text-transform: uppercase;">Catatan &amp; Insight Aktivitas:</div>
                        <p style="color: #5C410F; font-size: 0.8125rem; margin-top: 0.35rem; line-height: 1.5;">
                            {{ $habit['recommendation'] }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- JADWAL RESERVASI PADEL -->
            <div class="adm-card">
                <div class="adm-card-head">
                    <div>
                        <div class="adm-card-title">Jadwal &amp; Riwayat Reservasi Lapangan Padel ({{ $bookings->count() }})</div>
                        <div style="font-size: 0.75rem; color: #7A643E; margin-top: 0.2rem;">
                            Daftar seluruh sesi tanding yang pernah atau akan dimainkan oleh member ini di Club 61.
                        </div>
                    </div>
                </div>

                <div class="adm-table-wrap">
                    <table class="adm-table">
                        <thead>
                            <tr style="border-bottom: 2px solid #E5D5B3; background: rgba(250, 242, 222, 0.4);">
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Kode &amp; Tanggal</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Jam Bermain</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Lapangan</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Status Main</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Penggunaan Kuota</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Waktu Check-in Kasir</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bookings as $b)
                                <tr style="border-bottom: 1px solid #F0E6D2;">
                                    <td style="padding: 0.85rem 1rem;">
                                        <div style="font-weight: 800; color: #1F170D; font-size: 0.875rem;">{{ $b->booking_date->format('d M Y') }}</div>
                                        <div style="font-family: var(--font-mono); font-size: 0.75rem; color: #8C6418;">{{ $b->booking_code }}</div>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <div style="font-weight: 800; color: #1F170D;">
                                            {{ $b->start_time->format('H:i') }} - {{ $b->end_time->format('H:i') }} WIB
                                        </div>
                                        <div style="font-size: 0.6875rem; color: #7A643E;">
                                            Durasi: {{ $b->start_time->diffInHours($b->end_time) }} Jam
                                        </div>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 800; color: #5C410F;">
                                        {{ $b->court->name ?? 'Lapangan Padel' }}
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        @if($b->status === 'CHECKED_IN')
                                            <span class="adm-pill adm-pill-green">CHECKED IN</span>
                                        @elseif($b->status === 'CONFIRMED')
                                            <span class="adm-pill" style="background: #EAF2F8; color: #2874A6; border: 1px solid #AED6F1;">JADWAL MENDATANG</span>
                                        @elseif($b->status === 'COMPLETED')
                                            <span class="adm-pill adm-pill-gold">SELESAI MAIN</span>
                                        @else
                                            <span class="adm-pill" style="background: #FDEDEC; color: #C0392B;">{{ $b->status }}</span>
                                        @endif
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 800;">
                                        @if($b->member_hours_consumed > 0)
                                            <span style="color: #1E7E34;">-{{ (float)$b->member_hours_consumed }} Jam Kuota</span>
                                        @else
                                            <span style="color: #7A643E;">Tarif Normal / Diskon</span>
                                        @endif
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #665033;">
                                        {{ $b->checked_in_at ? $b->checked_in_at->format('d M Y, H:i') . ' WIB' : 'Belum Check-in' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding: 2.5rem 1rem; text-align: center; color: #8C7A58; font-size: 0.875rem;">
                                        Belum ada jadwal atau riwayat reservasi lapangan padel untuk member ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CHECK-IN GYM & SAUNA -->
            <div class="adm-card">
                <div class="adm-card-head">
                    <div class="adm-card-title">Riwayat Kunjungan Fasilitas Fisik (Gym &amp; Sauna Turnstile)</div>
                </div>

                <div class="adm-table-wrap">
                    <table class="adm-table">
                        <thead>
                            <tr style="border-bottom: 2px solid #E5D5B3; background: rgba(250, 242, 222, 0.4);">
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Waktu Check-in</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Fasilitas</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Status Gate Turnstile</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Pintu / Pemroses</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($checkins as $c)
                                <tr style="border-bottom: 1px solid #F0E6D2;">
                                    <td style="padding: 0.85rem 1rem; font-weight: 800; color: #1F170D;">
                                        {{ $c->checkin_at->format('d M Y, H:i') }} WIB
                                        <span style="font-size: 0.75rem; font-weight: normal; color: #7A643E;">({{ $c->checkin_at->diffForHumans() }})</span>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 800; color: #5C410F;">
                                        {{ $c->facility === 'GYM' ? 'Fitness & Gym Club 61' : 'Finnish Sauna & Cold Plunge' }}
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <span class="adm-pill adm-pill-green">AKSES TERVERIFIKASI</span>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #665033;">
                                        Auto Gate Turnstile Scanner
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" style="padding: 2rem 1rem; text-align: center; color: #8C7A58; font-size: 0.875rem;">
                                        Belum ada catatan check-in fasilitas gym atau sauna.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- ==========================================
             SUB-TAB 2: ANGGOTA TIM SPONSOR (CORPORATE)
             ========================================== -->
        @if($detailTab === 'corporate_roster' && $m->owner_type === 'ORGANIZATIONAL')
            <div class="adm-card" style="border: 2px solid #DFC387;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <span class="adm-pill" style="background: #F4ECF7; color: #6C3483; border: 1px solid #D2B4DE;">
                            Corporate Sponsor Pool System
                        </span>
                        <div class="adm-banner-title" style="font-size: 1.35rem; margin-top: 0.35rem;">
                            Daftar Anggota Karyawan &amp; Pemantauan Kuota Tim
                        </div>
                        <div style="font-size: 0.8125rem; color: #7A643E; margin-top: 0.2rem;">
                            Admin Sponsor (PIC HR) mendaftarkan personil untuk memanfaatkan kuota blok jam perusahaan. Sistem memantau anggota yang aktif bermain dan yang pasif/kosong.
                        </div>
                    </div>

                    <div style="display: flex; gap: 1.5rem; font-size: 0.8125rem; background: #FAF5E8; padding: 0.85rem 1.35rem; border-radius: 16px; border: 1.5px solid #DFC387;">
                        <div>
                            <div style="color: #7A643E;">Total Kuota Pool:</div>
                            <div style="font-weight: 900; font-size: 1.25rem; color: #8C6418;">120.0 Jam</div>
                        </div>
                        <div>
                            <div style="color: #7A643E;">Terpakai Tim:</div>
                            <div style="font-weight: 900; font-size: 1.25rem; color: #C0392B;">12.0 Jam</div>
                        </div>
                        <div>
                            <div style="color: #7A643E;">Sisa Kuota Bersama:</div>
                            <div style="font-weight: 900; font-size: 1.25rem; color: #1E7E34;">108.0 Jam</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Anggota Karyawan -->
            <div class="adm-card">
                <div class="adm-card-head">
                    <div class="adm-card-title">Daftar Karyawan Terdaftar ({{ count($roster) }} Orang)</div>
                    <div style="font-size: 0.75rem; color: #7A643E;">
                        Status: 3 Aktif Bermain &bull; 3 Kosong / Pasif Gak Ada Kabar
                    </div>
                </div>

                <div class="adm-table-wrap">
                    <table class="adm-table">
                        <thead>
                            <tr style="border-bottom: 2px solid #E5D5B3; background: rgba(250, 242, 222, 0.4);">
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Nama Karyawan</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Kontak WhatsApp</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Jatah &amp; Terpakai</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Status Aktivitas</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Jadwal Terakhir Main</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Jadwal Selanjutnya</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roster as $r)
                                <tr style="border-bottom: 1px solid #F0E6D2; {{ $r['status'] === 'IDLE' ? 'background: rgba(253, 237, 236, 0.45);' : '' }}">
                                    <td style="padding: 0.85rem 1rem;">
                                        <div style="font-weight: 800; color: #1F170D; font-size: 0.875rem;">{{ $r['name'] }}</div>
                                        <div style="font-size: 0.75rem; color: #8C6418;">{{ $r['role'] }}</div>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <div style="color: #1F170D; font-size: 0.8125rem;">{{ $r['email'] }}</div>
                                        <div style="font-size: 0.75rem; color: #1E7E34; font-weight: 700;">{{ $r['phone'] }}</div>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <div style="font-weight: 800; color: #1F170D;">
                                            <span style="color: #C0392B;">{{ $r['used_hours'] }} Jam</span> / {{ $r['allocated_hours'] }} Jam
                                        </div>
                                        <div style="font-size: 0.6875rem; color: #1E7E34; font-weight: 700;">
                                            Sisa Jatah: {{ $r['remaining_hours'] }} Jam
                                        </div>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        @if($r['status'] === 'ACTIVE')
                                            <span class="adm-pill adm-pill-green">AKTIF BERMAIN</span>
                                        @else
                                            <span class="adm-pill" style="background: #FDEDEC; color: #C0392B; border: 1px solid #F5B7B1;">
                                                KOSONG / PASIF
                                            </span>
                                        @endif
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 600; color: #1F170D; font-size: 0.8125rem;">
                                        {{ $r['last_played'] ?? 'Belum pernah main' }}
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 700; color: #8C6418; font-size: 0.8125rem;">
                                        {{ $r['next_schedule'] ?? 'Tidak ada jadwal' }}
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-size: 0.75rem; color: #665033;">
                                        {{ $r['notes'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Action Blast Pengingat Kuota -->
            <div class="adm-card" style="background: #FFFDF9; border: 1.5px solid #DFC387; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div style="font-weight: 800; color: #1F170D; font-size: 0.95rem;">Ada 3 Anggota Tim Belum Memakai Jatah Kuota</div>
                    <div style="font-size: 0.8125rem; color: #7A643E; margin-top: 0.2rem;">
                        Kirim pesan pengingat ke Admin Sponsor / PIC HR agar jatah kuota dimanfaatkan oleh karyawan sebelum masa aktif 90 hari berakhir.
                    </div>
                </div>

                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $m->user->phone ?? '081311223344') }}?text=Halo%20Admin%20Sponsor%20Club%2061%2C%20mengingatkan%20bahwa%20masih%20ada%20108%20Jam%20kuota%20Padel%20PT%20Sinar%20Harapan%20yang%20siap%20digunakan%20karyawan.%20Silakan%20reservasi%20lapangan%20sekarang."
                   target="_blank"
                   style="background: #1E7E34; color: #FFF; font-weight: 800; font-size: 0.8125rem; padding: 0.65rem 1.25rem; border-radius: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 12px rgba(30,126,52,0.25);">
                    <span>Blast Pengingat via WhatsApp &rarr;</span>
                </a>
            </div>
        @endif

        <!-- ==========================================
             SUB-TAB 3: KARTU DIGITAL & INFO ORDER
             ========================================== -->
        @if($detailTab === 'card_info')
            <div class="adm-card">
                <div class="adm-card-head">
                    <div class="adm-card-title">Informasi Detail Kartu &amp; Transaksi Pembelian</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; font-size: 0.8125rem;">
                    <div style="background: #FAF5E8; border: 1.5px solid #DFC387; border-radius: 18px; padding: 1.25rem;">
                        <div style="font-weight: 800; color: #5C410F; font-size: 0.875rem; border-bottom: 1px solid #DFC387; padding-bottom: 0.5rem; margin-bottom: 0.75rem;">
                            Data Akun Member
                        </div>
                        <div style="space-y: 0.5rem;">
                            <div>Nama Lengkap: <strong>{{ $m->user->name ?? '-' }}</strong></div>
                            <div>Email: <strong>{{ $m->user->email ?? '-' }}</strong></div>
                            <div>WhatsApp: <strong>{{ $m->user->phone ?? '-' }}</strong></div>
                            <div>Tipe Akun: <strong>{{ $m->owner_type }}</strong></div>
                            <div>Didaftarkan Oleh: <strong>{{ $m->soldByAdmin->name ?? 'Online Self-Service' }}</strong></div>
                        </div>
                    </div>

                    <div style="background: #FAF5E8; border: 1.5px solid #DFC387; border-radius: 18px; padding: 1.25rem;">
                        <div style="font-weight: 800; color: #5C410F; font-size: 0.875rem; border-bottom: 1px solid #DFC387; padding-bottom: 0.5rem; margin-bottom: 0.75rem;">
                            Rincian Finansial &amp; Masa Berlaku
                        </div>
                        <div style="space-y: 0.5rem;">
                            <div>Harga Paket Snapshot: <strong>Rp {{ number_format($m->purchase_price_snapshot, 0, ',', '.') }}</strong></div>
                            <div>Masa Aktif: <strong>{{ $m->start_date ? $m->start_date->format('d M Y') : '-' }} s/d {{ $m->end_date ? $m->end_date->format('d M Y') : '-' }}</strong></div>
                            <div>Status Saat Ini: <strong style="color: #1E7E34;">{{ $m->status }}</strong></div>
                            <div>QR Pass Barcode: <span style="font-family: var(--font-mono); font-size: 0.75rem;">{{ substr($m->qr_pass_hash ?? '-', 0, 24) }}...</span></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ==========================================
             SUB-TAB 4: AUDIT LOGS
             ========================================== -->
        @if($detailTab === 'audit_logs')
            <div class="adm-card">
                <div class="adm-card-head">
                    <div class="adm-card-title">Riwayat Mutasi &amp; Pemakaian Kuota (Audit Log Ledger)</div>
                </div>

                <div class="adm-table-wrap">
                    <table class="adm-table">
                        <thead>
                            <tr style="border-bottom: 2px solid #E5D5B3; background: rgba(250, 242, 222, 0.4);">
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Waktu Mutasi</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Fasilitas</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Tipe Aksi</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Besaran Perubahan</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Keterangan &amp; Petugas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $allLogs = collect();
                                foreach($m->balances as $bal) {
                                    $allLogs = $allLogs->concat($bal->usageLogs);
                                }
                                $allLogs = $allLogs->sortByDesc('created_at');
                            @endphp
                            @forelse($allLogs as $l)
                                <tr style="border-bottom: 1px solid #F0E6D2;">
                                    <td style="padding: 0.85rem 1rem; color: #665033; font-size: 0.8125rem;">
                                        {{ $l->created_at->format('d/m/Y H:i') }} WIB
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 800; color: #5C410F;">
                                        {{ $l->balance->facility ?? '-' }}
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <span class="adm-pill" style="background: {{ $l->change_type === 'DECREMENT' ? '#FDEDEC' : '#EAF7EC' }}; color: {{ $l->change_type === 'DECREMENT' ? '#C0392B' : '#1E7E34' }};">
                                            {{ $l->change_type }}
                                        </span>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 900; font-family: var(--font-mono); font-size: 0.875rem; color: {{ $l->quantity < 0 ? '#C0392B' : '#1E7E34' }};">
                                        {{ $l->quantity > 0 ? '+' : '' }}{{ (float) $l->quantity }}
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #1F170D; font-size: 0.8125rem;">
                                        {{ $l->notes ?? '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="padding: 2.5rem 1rem; text-align: center; color: #8C7A58; font-size: 0.875rem;">
                                        Belum ada riwayat mutasi kuota untuk member ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    @else
        <!-- ========================================================
             DAFTAR MEMBER UTAMA & METRIK LIVE MONITORING
             ======================================================== -->
        
        <!-- Header Banner -->
        <div class="adm-banner">
            <div>
                <div class="adm-pill adm-pill-gold">
                    <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background-color:#D4AF37;"></span>
                    <span>Modul 05 &bull; Customer Directory &amp; Live Monitoring</span>
                </div>
                <div class="adm-banner-title">
                    Data Kustomer &amp; Live Monitoring Membership
                </div>
                <div class="adm-banner-sub">
                    Pantau seluruh pelanggan terdaftar, kepemilikan kartu keanggotaan aktif, dan stream mutasi kuota fasilitas secara real-time.
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <a href="/admin/jual-membership" 
                   class="adm-btn-sec" 
                   style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05; border: 1px solid #FBF0CE; font-weight: 800; padding: 0.55rem 1.15rem; border-radius: 12px; font-size: 0.75rem; box-shadow: 0 4px 14px rgba(180, 130, 20, 0.2);">
                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>+ POS Jual Membership</span>
                </a>
            </div>
        </div>

        <!-- 4 Live Metric Cards -->
        <div class="adm-metrics-grid">
            <div class="adm-metric-card">
                <div class="adm-metric-label">Member Aktif Terdaftar</div>
                <div class="adm-metric-val">{{ $this->metrics['total_active'] }} <span style="font-size: 0.875rem; font-weight: 600; color: #7A643E;">Member</span></div>
                <div class="adm-metric-foot">
                    <span style="color: #1E7E34; font-weight: 700;">+{{ $this->metrics['new_this_month'] }} member baru</span> bulan ini
                </div>
            </div>
            <div class="adm-metric-card">
                <div class="adm-metric-label">Total Saldo Jam Padel Aktif</div>
                <div class="adm-metric-val" style="color: #8C6418;">{{ number_format($this->metrics['total_padel_hours'], 1) }} <span style="font-size: 0.875rem; font-weight: 600;">Jam</span></div>
                <div class="adm-metric-foot">
                    <span>Siap digunakan untuk reservasi lapangan</span>
                </div>
            </div>
            <div class="adm-metric-card">
                <div class="adm-metric-label">Pilihan Paket Membership</div>
                <div class="adm-metric-val">4 <span style="font-size: 0.875rem; font-weight: 600; color: #7A643E;">Tier</span></div>
                <div class="adm-metric-foot">
                    <span>Bronze, Silver, Gold, Corporate</span>
                </div>
            </div>
            <div class="adm-metric-card">
                <div class="adm-metric-label">Aktivitas Pemakaian Hari Ini</div>
                <div class="adm-metric-val" style="color: #1E7E34;">{{ $this->metrics['today_usage_count'] }} <span style="font-size: 0.875rem; font-weight: 600;">Log</span></div>
                <div class="adm-metric-foot">
                    <span>Padel, Gym turnstile &amp; Finnish sauna</span>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs Utama -->
        <div style="display: flex; gap: 0.5rem; border-bottom: 2px solid #E5D5B3; padding-bottom: 0.5rem;">
            <button type="button" 
                    wire:click="selectTab('members')"
                    style="padding: 0.6rem 1.25rem; border-radius: 12px; font-size: 0.8125rem; font-weight: 800; cursor: pointer; border: none; transition: all 0.2s; {{ $activeTab === 'members' ? 'background: #1F170D; color: #F5E5BE; box-shadow: 0 4px 12px rgba(31,23,13,0.25);' : 'background: rgba(255,255,255,0.7); color: #7A643E; border: 1px solid #DFC387;' }}">
                Daftar Member &amp; Keanggotaan ({{ $this->members->count() }})
            </button>
            <button type="button" 
                    wire:click="selectTab('live_monitoring')"
                    style="padding: 0.6rem 1.25rem; border-radius: 12px; font-size: 0.8125rem; font-weight: 800; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; {{ $activeTab === 'live_monitoring' ? 'background: #1F170D; color: #F5E5BE; box-shadow: 0 4px 12px rgba(31,23,13,0.25);' : 'background: rgba(255,255,255,0.7); color: #7A643E; border: 1px solid #DFC387;' }}">
                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: #28A745; box-shadow: 0 0 8px #28A745;"></span>
                <span>Live Monitoring Log Kuota Real-Time</span>
            </button>
        </div>

        @if($activeTab === 'members')
            <!-- TAB 1: DAFTAR MEMBER AKTIF & TERDAFTAR -->
            <div class="adm-card">
                <div class="adm-card-head" style="flex-wrap: wrap; gap: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1; min-width: 280px;">
                        <input type="text" 
                               wire:model.live.debounce.300ms="search"
                               placeholder="Cari nama pelanggan, email, nomor WhatsApp, kode kartu..." 
                               class="adm-search-input" 
                               style="width: 100%; max-width: 420px; padding: 0.6rem 1rem; border-radius: 12px; border: 1.5px solid #DFC387; background: #FFF; font-size: 0.8125rem; color: #1F170D;" />
                        
                        <select wire:model.live="statusFilter"
                                style="padding: 0.6rem 1rem; border-radius: 12px; border: 1.5px solid #DFC387; background: #FFF; font-size: 0.8125rem; font-weight: 700; color: #5C410F;">
                            <option value="ALL">Semua Status</option>
                            <option value="ACTIVE">Hanya Status ACTIVE</option>
                            <option value="PENDING_PAYMENT">Hanya PENDING PAYMENT</option>
                            <option value="EXPIRED">Hanya EXPIRED</option>
                        </select>
                    </div>

                    <div style="font-size: 0.75rem; font-weight: 700; color: #7A643E;">
                        Menampilkan {{ $this->members->count() }} Data Member
                    </div>
                </div>

                <div class="adm-table-wrap" style="overflow-x: auto;">
                    <table class="adm-table" style="width: 100%; text-align: left; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #E5D5B3; background: rgba(250, 242, 222, 0.4);">
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Pelanggan</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Kartu Member</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Paket Terpilih</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Saluran Beli</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Sisa Saldo Kuota (Padel / Gym / Sauna)</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Masa Berlaku</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Status</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800; text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($this->members as $mbr)
                                @php
                                    $pBal = $mbr->balances->firstWhere('facility', 'PADEL');
                                    $gBal = $mbr->balances->firstWhere('facility', 'GYM');
                                    $sBal = $mbr->balances->firstWhere('facility', 'SAUNA');
                                @endphp
                                <tr style="border-bottom: 1px solid #F0E6D2; transition: background 0.15s;" onmouseover="this.style.background='rgba(250,242,222,0.3)'" onmouseout="this.style.background='transparent'">
                                    <!-- Pelanggan -->
                                    <td style="padding: 0.85rem 1rem;">
                                        <div style="font-weight: 800; color: #1F170D; font-size: 0.875rem;">
                                            {{ $mbr->user->name ?? 'Tamu Walk-In' }}
                                        </div>
                                        <div style="font-size: 0.75rem; color: #665033; display: flex; align-items: center; gap: 0.35rem; margin-top: 0.15rem;">
                                            <span>{{ $mbr->user->email ?? '-' }}</span>
                                            <span>&bull;</span>
                                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $mbr->user->phone ?? '') }}" target="_blank" style="color: #1E7E34; font-weight: 700; text-decoration: none;">
                                                {{ $mbr->user->phone ?? '-' }}
                                            </a>
                                        </div>
                                    </td>

                                    <!-- Kartu Member -->
                                    <td style="padding: 0.85rem 1rem;">
                                        <div style="font-family: var(--font-mono); font-weight: 800; color: #8C6418; font-size: 0.8125rem;">
                                            {{ $mbr->membership_code }}
                                        </div>
                                        <div style="font-size: 0.6875rem; color: #8C7A58; margin-top: 0.15rem;">
                                            Daftar: {{ $mbr->created_at->format('d M Y') }}
                                        </div>
                                    </td>

                                    <!-- Paket -->
                                    <td style="padding: 0.85rem 1rem;">
                                        <span class="adm-pill adm-pill-gold" style="font-weight: 800;">
                                            {{ $mbr->plan->name ?? '-' }}
                                        </span>
                                        <div style="font-size: 0.6875rem; color: #7A643E; margin-top: 0.25rem;">
                                            {{ $mbr->owner_type === 'ORGANIZATIONAL' ? 'Corporate / Sponsor' : 'Individual' }}
                                        </div>
                                    </td>

                                    <!-- Saluran Beli -->
                                    <td style="padding: 0.85rem 1rem;">
                                        @if($mbr->sold_by_admin_id)
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.6rem; border-radius: 8px; font-size: 0.6875rem; font-weight: 800; background: #FAF2DE; color: #8C6418; border: 1px solid #D9BE84;">
                                                POS Frontdesk
                                            </span>
                                            <div style="font-size: 0.65rem; color: #7A643E; margin-top: 0.15rem;">
                                                Kasir: {{ $mbr->soldByAdmin->name ?? 'Frontdesk' }}
                                            </div>
                                        @else
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.6rem; border-radius: 8px; font-size: 0.6875rem; font-weight: 800; background: #EAF2F8; color: #1B4F72; border: 1px solid #A9CCE3;">
                                                Online Midtrans
                                            </span>
                                            <div style="font-size: 0.65rem; color: #5499C7; margin-top: 0.15rem;">
                                                Web Customer
                                            </div>
                                        @endif
                                    </td>

                                    <!-- Sisa Kuota 3 Fasilitas -->
                                    <td style="padding: 0.85rem 1rem;">
                                        <div style="display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.75rem;">
                                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                                                <span style="font-weight: 700; color: #5C410F;">Padel:</span>
                                                <span style="font-weight: 800; color: #1F170D;">
                                                    @if($pBal && $pBal->quota_type === 'HOURS')
                                                        <span style="color: #1E7E34;">{{ (float) $pBal->remaining_quota }}</span> / {{ (float) $pBal->initial_quota }} Jam
                                                    @elseif($pBal && $pBal->quota_type === 'NONE')
                                                        <span style="color: #8C6418;">Diskon {{ $pBal->discount_percent }}%</span>
                                                    @else
                                                        -
                                                    @endif
                                                </span>
                                            </div>

                                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                                                <span style="font-weight: 700; color: #5C410F;">Gym:</span>
                                                <span style="font-weight: 800; color: #1F170D;">
                                                    @if($gBal && $gBal->initial_quota)
                                                        <span style="color: #1E7E34;">{{ (float) $gBal->remaining_quota }}</span> / {{ (float) $gBal->initial_quota }} Sesi
                                                    @elseif($gBal)
                                                        <span style="color: #1E7E34;">Unlimited</span>
                                                    @else
                                                        -
                                                    @endif
                                                </span>
                                            </div>

                                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                                                <span style="font-weight: 700; color: #5C410F;">Sauna:</span>
                                                <span style="font-weight: 800; color: #1F170D;">
                                                    @if($sBal && $sBal->initial_quota)
                                                        <span style="color: #1E7E34;">{{ (float) $sBal->remaining_quota }}</span> / {{ (float) $sBal->initial_quota }} Sesi
                                                    @elseif($sBal)
                                                        <span style="color: #1E7E34;">Unlimited</span>
                                                    @else
                                                        -
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Masa Berlaku -->
                                    <td style="padding: 0.85rem 1rem;">
                                        <div style="font-size: 0.75rem; font-weight: 700; color: #1F170D;">
                                            {{ $mbr->start_date ? $mbr->start_date->format('d M Y') : '-' }} s/d {{ $mbr->end_date ? $mbr->end_date->format('d M Y') : '-' }}
                                        </div>
                                        <div style="font-size: 0.6875rem; color: #8C7A58; margin-top: 0.15rem;">
                                            @if($mbr->end_date)
                                                @if($mbr->end_date->isPast())
                                                    <span style="color: #C0392B; font-weight: 700;">Lewat {{ $mbr->end_date->diffForHumans() }}</span>
                                                @else
                                                    <span style="color: #1E7E34; font-weight: 700;">Sisa {{ now()->diffInDays($mbr->end_date, false) }} hari lagi</span>
                                                @endif
                                            @endif
                                        </div>
                                    </td>

                                    <!-- Status Badge -->
                                    <td style="padding: 0.85rem 1rem;">
                                        @if($mbr->status === 'ACTIVE')
                                            <span class="adm-pill adm-pill-green">ACTIVE</span>
                                        @elseif($mbr->status === 'PENDING_PAYMENT')
                                            <span class="adm-pill" style="background: #FEF9E7; color: #B7950B; border: 1px solid #F9E79F;">
                                                PENDING BAYAR
                                            </span>
                                        @elseif($mbr->status === 'EXPIRED')
                                            <span class="adm-pill" style="background: #FDEDEC; color: #C0392B; border: 1px solid #F5B7B1;">
                                                EXPIRED
                                            </span>
                                        @else
                                            <span class="adm-pill" style="background: #EAECEE; color: #5D6D7E; border: 1px solid #D5D8DC;">
                                                {{ $mbr->status }}
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Action: Buka Halaman Penuh Track Record -->
                                    <td style="padding: 0.85rem 1rem; text-align: right;">
                                        <button type="button" 
                                                wire:click="openDetail('{{ $mbr->id }}')"
                                                style="padding: 0.45rem 0.95rem; border-radius: 10px; font-size: 0.75rem; font-weight: 800; background: linear-gradient(135deg, #FAF2DE 0%, #F5E5BE 100%); border: 1.5px solid #DFC387; color: #7A5818; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(180,130,20,0.1);"
                                                onmouseover="this.style.background='#F3DFAD'" onmouseout="this.style.background='linear-gradient(135deg, #FAF2DE 0%, #F5E5BE 100%)'">
                                            Monitoring &amp; Track Record &rarr;
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" style="padding: 3rem 1rem; text-align: center; color: #8C7A58; font-size: 0.875rem;">
                                        Tidak ada data member yang sesuai dengan pencarian atau filter status.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <!-- TAB 2: LIVE MONITORING LOG PENGGUNAAN KUOTA REAL-TIME -->
            <div class="adm-card">
                <div class="adm-card-head" style="flex-wrap: wrap; gap: 0.75rem;">
                    <div>
                        <div class="adm-card-title" style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: #28A745; box-shadow: 0 0 8px #28A745;"></span>
                            <span>Stream Mutasi &amp; Pemakaian Kuota (Live Audit Feed)</span>
                        </div>
                        <div style="font-size: 0.75rem; color: #7A643E; margin-top: 0.25rem;">
                            Seluruh aktivitas pemotongan jam lapangan, check-in gym turnstile, sauna, top-up kuota, dan reversal pembatalan tercatat secara permanen tanpa dapat diubah.
                        </div>
                    </div>

                    <div style="font-size: 0.75rem; font-weight: 700; color: #8C6418;">
                        50 Log Terakhir
                    </div>
                </div>

                <div class="adm-table-wrap" style="overflow-x: auto;">
                    <table class="adm-table" style="width: 100%; text-align: left; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #E5D5B3; background: rgba(250, 242, 222, 0.4);">
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Waktu Kejadian</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Pelanggan &amp; Kartu</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Fasilitas</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Aksi / Tipe Mutasi</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Perubahan Kuota</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Sisa Kuota Terkini</th>
                                <th style="padding: 0.85rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: #5C410F; font-weight: 800;">Keterangan &amp; Petugas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($this->liveLogs as $log)
                                @php
                                    $mLog = $log->balance->membership ?? null;
                                    $uLog = $mLog->user ?? null;
                                @endphp
                                <tr style="border-bottom: 1px solid #F0E6D2; transition: background 0.15s;" onmouseover="this.style.background='rgba(250,242,222,0.3)'" onmouseout="this.style.background='transparent'">
                                    <!-- Waktu -->
                                    <td style="padding: 0.85rem 1rem; font-size: 0.75rem;">
                                        <div style="font-weight: 800; color: #1F170D;">
                                            {{ $log->created_at->format('d M Y, H:i') }} WIB
                                        </div>
                                        <div style="font-size: 0.6875rem; color: #8C7A58; margin-top: 0.1rem;">
                                            {{ $log->created_at->diffForHumans() }}
                                        </div>
                                    </td>

                                    <!-- Pelanggan & Kartu -->
                                    <td style="padding: 0.85rem 1rem;">
                                        <div style="font-weight: 800; color: #1F170D; font-size: 0.8125rem;">
                                            {{ $uLog->name ?? 'Tamu Walk-In' }}
                                        </div>
                                        <div style="font-family: var(--font-mono); font-size: 0.6875rem; color: #8C6418;">
                                            {{ $mLog->membership_code ?? '-' }}
                                        </div>
                                    </td>

                                    <!-- Fasilitas -->
                                    <td style="padding: 0.85rem 1rem;">
                                        <span style="font-weight: 800; font-size: 0.75rem; color: #5C410F;">
                                            {{ $log->balance->facility ?? '-' }}
                                        </span>
                                        <div style="font-size: 0.6875rem; color: #8C7A58;">
                                            {{ $log->balance->quota_type ?? '-' }}
                                        </div>
                                    </td>

                                    <!-- Tipe Mutasi -->
                                    <td style="padding: 0.85rem 1rem;">
                                        @if($log->change_type === 'DECREMENT')
                                            <span class="adm-pill" style="background: #FDEDEC; color: #C0392B; border: 1px solid #F5B7B1;">
                                                DECREMENT (PAKAI)
                                            </span>
                                        @elseif($log->change_type === 'TOPUP')
                                            <span class="adm-pill adm-pill-green">
                                                TOPUP (ISI)
                                            </span>
                                        @elseif($log->change_type === 'REVERSAL')
                                            <span class="adm-pill" style="background: #EAF2F8; color: #2874A6; border: 1px solid #AED6F1;">
                                                REVERSAL (REFUND)
                                            </span>
                                        @elseif(str_contains($log->change_type, 'ROLLOVER'))
                                            <span class="adm-pill" style="background: #F4ECF7; color: #6C3483; border: 1px solid #D2B4DE;">
                                                {{ $log->change_type }}
                                            </span>
                                        @else
                                            <span class="adm-pill adm-pill-gold">
                                                {{ $log->change_type }}
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Perubahan Kuota -->
                                    <td style="padding: 0.85rem 1rem;">
                                        <span style="font-weight: 900; font-size: 0.875rem; font-family: var(--font-mono); {{ $log->quantity < 0 ? 'color: #C0392B;' : 'color: #1E7E34;' }}">
                                            {{ $log->quantity > 0 ? '+' : '' }}{{ (float) $log->quantity }}
                                        </span>
                                        <span style="font-size: 0.6875rem; color: #7A643E;">
                                            {{ $log->balance->quota_type === 'HOURS' ? 'Jam' : 'Sesi' }}
                                        </span>
                                    </td>

                                    <!-- Sisa Kuota Terkini -->
                                    <td style="padding: 0.85rem 1rem;">
                                        <span style="font-weight: 800; font-size: 0.8125rem; color: #1F170D;">
                                            {{ (float) ($log->balance->remaining_quota ?? 0) }}
                                        </span>
                                        <span style="font-size: 0.6875rem; color: #8C7A58;">
                                            {{ $log->balance->quota_type === 'HOURS' ? 'Jam' : 'Sesi' }}
                                        </span>
                                    </td>

                                    <!-- Keterangan & Petugas -->
                                    <td style="padding: 0.85rem 1rem; font-size: 0.75rem;">
                                        <div style="font-weight: 600; color: #1F170D;">
                                            {{ $log->notes ?? '-' }}
                                        </div>
                                        <div style="font-size: 0.6875rem; color: #8C7A58; margin-top: 0.15rem;">
                                            Operator: {{ $log->performer->name ?? 'Sistem Otomatis / Turnstile Gate' }}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" style="padding: 3rem 1rem; text-align: center; color: #8C7A58; font-size: 0.875rem;">
                                        Belum ada log aktivitas pemakaian kuota yang tercatat.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
