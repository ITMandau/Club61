{{-- Daftar coach satu sesi coaching + tautan WhatsApp pengingat (App\Services\Coaching\CoachingReminder). --}}
@php
    $coaches = $booking->coachingBooking?->coaches->sortBy(fn ($c) => $c->pivot->role === 'HEAD' ? 0 : 1) ?? collect();
@endphp
<div style="display: flex; flex-direction: column; gap: 0.6rem;">
    @forelse ($coaches as $coach)
        @php
            $url = \App\Services\Coaching\CoachingReminder::url($coach, $booking);
        @endphp
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.7rem 0.9rem; border: 1px solid #E6DAC0; border-radius: 12px;">
            <div style="min-width: 0;">
                <div style="font-weight: 800; color: #4F2F2A;">{{ $coach->name }}</div>
                <div style="font-size: 0.75rem; color: #7A5A52;">{{ $coach->pivot->role === 'HEAD' ? 'Coach utama' : 'Coach tambahan' }} · {{ $coach->phone ?: 'No. WhatsApp belum diisi' }}</div>
            </div>
            @if ($url)
                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                   style="flex-shrink: 0; padding: 0.45rem 0.85rem; border-radius: 10px; background: #15803D; color: #FFFFFF; font-weight: 800; font-size: 0.8125rem; text-decoration: none;">WhatsApp</a>
            @else
                <span style="flex-shrink: 0; font-size: 0.75rem; color: #B42318;">Isi no. WA di menu Coach</span>
            @endif
        </div>
    @empty
        <div style="font-size: 0.8125rem; color: #7A5A52;">Data coach sesi ini tidak ditemukan.</div>
    @endforelse
</div>
