<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\BookingSystem;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingSystemCheckInTest extends TestCase
{
    use RefreshDatabase;

    private function paidBookingStartingSoon(string $code, string $hash): PadelBooking
    {
        $court = PadelCourt::create([
            'name' => 'Court '.$code,
            'type' => 'INDOOR',
            'hourly_rate_regular' => 200000,
            'hourly_rate_prime' => 300000,
            'is_active' => true,
        ]);
        // Check-in dibuka 45 menit sebelum sesi dimulai.
        $start = now()->addMinutes(10)->startOfMinute();

        return PadelBooking::create([
            'booking_code' => $code,
            'user_id' => User::factory()->customer()->create()->id,
            'court_id' => $court->id,
            'booking_date' => $start->format('Y-m-d'),
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'court_fee' => 200000,
            'total_amount' => 200000,
            'status' => 'PAID',
            'qr_code_hash' => $hash,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_gate_modal_form_is_valid_livewire_markup(): void
    {
        // Regresi: form dulu berisi kode PHP di atribut wire:submit — dieksekusi sebagai JS di
        // browser → "SyntaxError: Invalid or unexpected token", tombol check-in mati total.
        Livewire::test(BookingSystem::class)
            ->call('openCheckInModal')
            ->assertSeeHtml('wire:submit.prevent="executeCheckIn"')
            ->assertDontSeeHtml('executeCheckIn(app(');
    }

    public function test_check_in_by_typed_booking_code_and_by_scanned_qr_hash(): void
    {
        $byCode = $this->paidBookingStartingSoon('BK-PAD-KODE01', 'hash-kode-01');
        $byHash = $this->paidBookingStartingSoon('BK-PAD-KODE02', 'hash-scan-02');

        Livewire::test(BookingSystem::class)
            ->call('openCheckInModal')
            ->set('checkInQuery', 'BK-PAD-KODE01')
            ->call('executeCheckIn')
            ->set('checkInQuery', 'hash-scan-02')
            ->call('executeCheckIn');

        $this->assertSame('CHECKED_IN', $byCode->fresh()->status);
        $this->assertSame('CHECKED_IN', $byHash->fresh()->status);
    }

    public function test_quick_check_in_from_booking_detail_panel(): void
    {
        $booking = $this->paidBookingStartingSoon('BK-PAD-KLIK03', 'hash-klik-03');

        Livewire::test(BookingSystem::class)
            ->call('inspectBooking', $booking->id)
            ->call('quickCheckInFromInspector', $booking->id);

        $this->assertSame('CHECKED_IN', $booking->fresh()->status);
    }
}
