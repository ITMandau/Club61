<?php

namespace Tests\Feature\Padel;

use App\Exceptions\SlotConflictException;
use App\Filament\Pages\BookOfflineCourt;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\PosCashierShift;
use App\Models\User;
use App\Services\Padel\BookingTimeService;
use App\Services\Padel\PadelBookingService;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * POS walk-in menahan slot saat "Lanjut ke Pembayaran" (seperti customer online menekan Continue). Dulu slot baru
 * dikunci saat Bayar ditekan — customer bisa sudah menggesek kartu, lalu ternyata slotnya keburu dibooking online.
 */
class WalkInHoldBeforePaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private PadelCourt $court;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');

        $this->cashier = User::factory()->cashier()->create(['name' => 'Kasir Pagi', 'is_active' => true]);
        $this->cashier->givePermissionTo(['View:BookOfflineCourt', 'process_walkin_booking']);
        $this->court = PadelCourt::create(['name' => 'Court A', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 200000, 'is_active' => true]);
        $this->date = Carbon::parse('next Tuesday')->format('Y-m-d');

        PosCashierShift::create([
            'shift_number' => PosCashierShift::generateShiftNumber('PADEL_FRONTDESK'), 'counter' => 'PADEL_FRONTDESK', 'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id, 'opened_at' => now(), 'starting_cash' => 0, 'expected_cash' => 0,
        ]);
        $this->actingAs($this->cashier);
    }

    private function slot(string $hour): array
    {
        return ["{$this->court->id}_{$hour}:00:00" => [
            'court_id' => $this->court->id, 'court_name' => 'Court A', 'start_time' => "{$hour}:00:00",
            'end_time' => sprintf('%02d:00:00', (int) $hour + 1), 'time_label' => "{$hour}:00", 'price' => 200000,
        ]];
    }

    private function pos(array $slots)
    {
        return Livewire::test(BookOfflineCourt::class)
            ->set('bookingDate', $this->date)
            ->set('selectedSlots', $slots)
            ->set('customerMode', 'quick_create')
            ->set('walkInName', 'Budi Walk-In')
            ->set('walkInPhone', '081299887766');
    }

    private function payByEdc($page, string $approval = 'APPR01')
    {
        return $page->set('paymentMethod', 'EDC_BCA')->set('edcLast4', '1234')->set('edcApprovalCode', $approval)->set('edcTraceNumber', 'TRC001')
            ->call('submitWalkInBooking');
    }

    private function onlineHold(string $hour): array
    {
        return app(PadelBookingService::class)->holdBatchSlots(
            [['court_id' => $this->court->id, 'start_time' => "{$hour}:00", 'end_time' => sprintf('%02d:00', (int) $hour + 1)]],
            $this->date,
            User::factory()->customer()->create(['name' => 'Customer Online'])
        );
    }

    public function test_slot_is_held_when_the_cashier_goes_to_payment_so_online_customers_cannot_take_it(): void
    {
        $page = $this->pos($this->slot('14'))->call('proceedToPayment')->assertSet('posStep', 'payment');

        $hold = PadelBooking::sole();
        $this->assertSame('LOCKED', $hold->status);
        $this->assertSame($this->cashier->id, $hold->user_id, 'ditahan atas nama kasir sampai dibayar (customer belum tentu jadi)');
        $this->assertSame([$hold->id], $page->get('heldBookingIds'));
        $this->assertNotNull($page->get('heldUntil'));
        $page->assertSee('Slot sudah ditahan untuk customer ini');

        $this->expectException(SlotConflictException::class);
        $this->onlineHold('14');
    }

    public function test_payment_turns_the_held_slot_into_the_customers_booking(): void
    {
        $page = $this->pos($this->slot('14'))->call('proceedToPayment');
        $held = PadelBooking::sole();

        $this->payByEdc($page)->assertSet('posStep', 'receipt')->assertSet('heldBookingIds', []);

        $this->assertSame(1, PadelBooking::count(), 'tahanan dipakai, bukan dibuat booking baru');
        $booking = $held->fresh();
        $this->assertSame('PAID', $booking->status);
        $this->assertSame('Budi Walk-In', $booking->user->name);
        $this->assertNotSame($held->qr_code_hash, $booking->qr_code_hash, 'kode QR tiket mengikuti pemilik barunya');
        $this->assertSame('PAID', Order::sole()->payment_status);
    }

    public function test_slot_taken_online_before_payment_is_flagged_and_removed_without_charging_anyone(): void
    {
        $page = $this->pos($this->slot('14') + $this->slot('15'));
        $this->onlineHold('15');

        // Lewat update realtime: kasir melihat slot pilihannya "Diambil" sebelum lanjut.
        $page->call('$refresh')->assertSeeHtml('slot-taken')->assertSee('Baru diambil customer lain');

        $page->call('proceedToPayment')
            ->assertSet('posStep', 'selection')
            ->assertNotified('Slot Baru Saja Diambil');

        $this->assertSame(["{$this->court->id}_14:00:00"], array_keys($page->get('selectedSlots')), 'jam yang diambil orang dilepas, jam lain tetap');
        $this->assertSame(0, PadelBooking::where('user_id', $this->cashier->id)->count(), 'tidak ada tahanan setengah jadi');
        $this->assertSame(0, Order::count());

        // Lanjut lagi dengan jam yang tersisa → berhasil ditahan.
        $page->call('proceedToPayment')->assertSet('posStep', 'payment');
    }

    public function test_going_back_keeps_the_hold_and_changing_slots_releases_it(): void
    {
        $page = $this->pos($this->slot('14'))->call('proceedToPayment');
        $first = PadelBooking::sole();

        $page->call('backToSelection')->assertSet('posStep', 'selection');
        $this->assertSame('LOCKED', $first->fresh()->status, 'kasir hanya membetulkan data customer — slot tetap aman');

        $page->call('proceedToPayment');
        $this->assertSame([$first->id], $page->get('heldBookingIds'), 'tahanan yang sama dipakai lagi');

        $page->call('backToSelection')->call('toggleSlot', $this->court->id, 'Court A', '16:00:00', '17:00:00', 200000);
        $this->assertSame('CANCELLED', $first->fresh()->status, 'pilihan jam berubah → tahanan lama dilepas');
        $this->assertSame([], $page->get('heldBookingIds'));

        $page->call('startNewTransaction');
        $this->assertSame(0, PadelBooking::where('status', 'LOCKED')->count());
    }

    public function test_new_transaction_releases_a_hold_that_was_never_paid(): void
    {
        $page = $this->pos($this->slot('14'))->call('proceedToPayment');
        $page->call('startNewTransaction');

        $this->assertSame('CANCELLED', PadelBooking::sole()->status);
        $this->onlineHold('14'); // slot kosong lagi untuk customer lain
        $this->assertSame(2, PadelBooking::count());
    }

    public function test_expired_hold_is_held_again_at_payment_when_still_free(): void
    {
        $page = $this->pos($this->slot('14'))->call('proceedToPayment');
        $this->travel(app(BookingTimeService::class)->holdMinutes() + 1)->minutes();

        $this->payByEdc($page)->assertSet('posStep', 'receipt');

        $this->assertSame(1, PadelBooking::where('status', 'PAID')->count());
        $this->assertSame('Budi Walk-In', PadelBooking::where('status', 'PAID')->sole()->user->name);
    }

    public function test_expired_hold_taken_by_someone_else_is_refused_before_recording_payment(): void
    {
        $page = $this->pos($this->slot('14'))->call('proceedToPayment');
        $this->travel(app(BookingTimeService::class)->holdMinutes() + 1)->minutes();
        app(PadelBookingService::class)->releaseExpiredLocks();
        $this->onlineHold('14');

        $this->payByEdc($page)->assertSet('posStep', 'selection')->assertNotified('Slot Tidak Tersedia');
        $this->assertSame(0, Order::count());
    }

    public function test_failed_checkout_keeps_the_hold_so_the_cashier_can_retry(): void
    {
        $page = $this->pos($this->slot('14'))->call('proceedToPayment');
        $held = PadelBooking::sole();

        // Total di layar tidak cocok dengan server → checkout ditolak, tapi slotnya tetap ditahan.
        $refused = null;
        try {
            app(PadelBookingService::class)->processWalkInCheckout(
                customer: User::factory()->customer()->create(), slots: [['court_id' => $this->court->id, 'start_time' => '14:00:00', 'end_time' => '15:00:00']],
                bookingDate: $this->date, equipments: [], paymentMethod: 'EDC_BCA', cashier: $this->cashier,
                paymentMeta: ['terminal' => 'EDC_BCA', 'card_last_4' => '1234', 'approval_code' => 'X1', 'trace_number' => 'T1'],
                expectedGrandTotal: 1.0, heldBookingIds: [$held->id], holder: $this->cashier,
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $refused = $e;
        }
        $this->assertNotNull($refused, 'checkout dengan total berbeda seharusnya ditolak');
        $this->assertSame(0, Order::count());

        $this->assertSame('LOCKED', $held->fresh()->status);
        $this->assertSame($this->cashier->id, $held->fresh()->user_id);

        $this->payByEdc($page)->assertSet('posStep', 'receipt');
        $this->assertSame('PAID', $held->fresh()->status);
    }

    public function test_reloaded_page_resumes_the_draft_with_its_own_hold_not_as_a_conflict(): void
    {
        $this->pos($this->slot('14'))->call('proceedToPayment');
        $held = PadelBooking::sole();

        // Tablet kasir ter-refresh → draf dipulihkan; slot yang ditahan kasir sendiri bukan "bentrok".
        $page = Livewire::test(BookOfflineCourt::class)->assertSet('hasPendingDraft', true)->call('resumeDraft')
            ->assertSet('posStep', 'payment')
            ->assertNotified('Draf Transaksi Dipulihkan');
        $this->assertSame([$held->id], $page->get('heldBookingIds'));
        $this->assertSame(1, PadelBooking::count());

        $this->payByEdc($page)->assertSet('posStep', 'receipt');
        $this->assertSame('PAID', $held->fresh()->status);
    }

    public function test_held_slots_cannot_be_swapped_for_someone_elses_booking(): void
    {
        $page = $this->pos($this->slot('15'));
        $online = PadelBooking::findOrFail(collect($this->onlineHold('14')['bookings'])->first()->id);

        // Dari browser: properti dikunci.
        try {
            $page->set('heldBookingIds', [$online->id]);
            $this->fail('heldBookingIds seharusnya tidak bisa diubah dari browser');
        } catch (CannotUpdateLockedPropertyException) {
        }

        // Dari server: booking milik orang lain tidak pernah dianggap tahanan kasir.
        $this->assertFalse(app(PadelBookingService::class)->walkInHoldIsValid([$online->id], $this->cashier,
            [['court_id' => $this->court->id, 'start_time' => '14:00:00', 'end_time' => '15:00:00']], $this->date));
        $this->assertSame('LOCKED', $online->fresh()->status);
        $this->assertSame('Customer Online', $online->fresh()->user->name);
    }
}
