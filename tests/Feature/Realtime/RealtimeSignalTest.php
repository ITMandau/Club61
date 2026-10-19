<?php

namespace Tests\Feature\Realtime;

use App\Events\BookingBoardChanged;
use App\Events\SignatureChanged;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Refund;
use App\Models\Signature\SignatureTemplate;
use App\Models\User;
use App\Services\Finance\RefundQueueService;
use App\Services\Padel\Handlers\PadelFulfillmentHandler;
use App\Services\Padel\PadelBookingService;
use App\Services\Permission\Club61PermissionMatrix;
use App\Services\Realtime\RealtimeSignal;
use App\Services\Signature\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Sinyal realtime: setiap perubahan booking (lewat model maupun update massal query builder) memberi tahu layar jadwal,
 * dikirim sekali per request, dan Reverb yang mati tidak pernah menggagalkan transaksi.
 */
class RealtimeSignalTest extends TestCase
{
    use RefreshDatabase;

    private PadelCourt $court;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->court = PadelCourt::create(['name' => 'Court A', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true]);
        $this->customer = User::factory()->customer()->create();
        $this->signal()->flush(); // buang sinyal dari data awal
    }

    private function signal(): RealtimeSignal
    {
        return app(RealtimeSignal::class);
    }

    private function enableRealtime(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '1',
        ]);
    }

    private function booking(string $status, Carbon $start, array $extra = []): PadelBooking
    {
        $booking = PadelBooking::create(array_merge([
            'booking_code' => 'BK-RT-'.strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => $start->format('Y-m-d'),
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'court_fee' => 200000,
            'total_amount' => 200000,
            'status' => $status,
        ], $extra));
        $this->signal()->flush();

        return $booking;
    }

    public function test_changes_in_one_request_are_sent_once_with_all_dates(): void
    {
        $this->enableRealtime();
        Event::fake([BookingBoardChanged::class]);
        Cache::put(RealtimeSignal::TAB_COUNTS_CACHE, ['total' => 1], 60);

        $friday = Carbon::parse('2026-10-16 19:00');
        $first = $this->booking('LOCKED', $friday);
        Event::assertDispatchedTimes(BookingBoardChanged::class, 1);

        // Checkout 2 jam + 1 jam di hari lain dalam satu request → 1 sinyal berisi kedua tanggal.
        $first->update(['status' => 'PENDING_PAYMENT']);
        PadelBooking::create(['booking_code' => 'BK-RT-2', 'user_id' => $this->customer->id, 'court_id' => $this->court->id, 'booking_date' => '2026-10-16',
            'start_time' => $friday->copy()->addHour(), 'end_time' => $friday->copy()->addHours(2), 'court_fee' => 1, 'total_amount' => 1, 'status' => 'PENDING_PAYMENT']);
        PadelBooking::create(['booking_code' => 'BK-RT-3', 'user_id' => $this->customer->id, 'court_id' => $this->court->id, 'booking_date' => '2026-10-18',
            'start_time' => Carbon::parse('2026-10-18 08:00'), 'end_time' => Carbon::parse('2026-10-18 09:00'), 'court_fee' => 1, 'total_amount' => 1, 'status' => 'PENDING_PAYMENT']);
        $this->signal()->flush();

        Event::assertDispatchedTimes(BookingBoardChanged::class, 2);
        Event::assertDispatched(BookingBoardChanged::class, fn ($e) => $e->dates === ['2026-10-16', '2026-10-18']);
        $this->assertNull(Cache::get(RealtimeSignal::TAB_COUNTS_CACHE), 'hitungan tab Kelola Pemesanan ikut diperbarui');

        // Payload hanya tanggal — tanpa nama / kode booking di channel publik.
        $this->assertSame(['dates' => ['2026-10-16', '2026-10-18']], (new BookingBoardChanged(['2026-10-16', '2026-10-18']))->broadcastWith());
        $this->assertSame('booking-board', (new BookingBoardChanged([]))->broadcastOn()->name);
    }

    public function test_reschedule_signals_old_and_new_date_and_silent_changes_do_not(): void
    {
        $booking = $this->booking('PAID', Carbon::parse('2026-10-20 10:00'));

        $booking->update(['expires_at' => now()->addMinutes(5)]);
        $this->assertSame([], $this->signal()->pendingDates(), 'perpanjangan batas bayar tidak tampil di layar mana pun');

        $booking->update(['booking_date' => '2026-10-22', 'start_time' => Carbon::parse('2026-10-22 10:00'), 'end_time' => Carbon::parse('2026-10-22 11:00')]);
        $this->assertSame(['2026-10-20', '2026-10-22'], $this->signal()->pendingDates());
        $this->signal()->flush();

        $booking->delete();
        $this->assertSame(['2026-10-22'], $this->signal()->pendingDates());
        $this->signal()->flush();

        $this->court->update(['close_time' => '22:00']);
        $this->assertSame(['*'], $this->signal()->pendingDates(), 'jam buka lapangan berubah → semua tanggal');
    }

    public function test_mass_updates_that_skip_model_events_still_signal(): void
    {
        $service = app(PadelBookingService::class);
        $admin = User::factory()->superAdmin()->create();

        // Selesai bermain (update bersyarat).
        $playing = $this->booking('CHECKED_IN', now()->subMinutes(30), ['checked_in_at' => now()->subMinutes(30)]);
        $service->completeBooking($playing->id, $admin);
        $this->assertSame([$playing->booking_date->format('Y-m-d')], $this->signal()->pendingDates());
        $this->signal()->flush();

        // Sinkronisasi terjadwal: tidak datang → EXPIRED, sudah check-in & lewat → COMPLETED.
        $fourDaysAgo = now()->subDays(4)->setTime(8, 0);
        $threeDaysAgo = now()->subDays(3)->setTime(8, 0);
        $this->booking('PAID', $fourDaysAgo);
        $this->booking('CHECKED_IN', $threeDaysAgo, ['checked_in_at' => $threeDaysAgo]);
        $result = $service->syncExpiredAndCompletedBookings();
        $this->assertSame(1, $result['expired']);
        $this->assertSame(1, $result['completed']);
        $this->assertSame([$fourDaysAgo->format('Y-m-d'), $threeDaysAgo->format('Y-m-d')], $this->signal()->pendingDates());
        $this->signal()->flush();

        // Keranjang yang waktu tahannya habis.
        $hold = $this->booking('LOCKED', Carbon::parse('2026-10-25 18:00'), ['expires_at' => now()->subMinute()]);
        $this->assertSame(1, $service->releaseExpiredLocks());
        $this->assertSame('EXPIRED', $hold->fresh()->status);
        $this->assertSame(['2026-10-25'], $this->signal()->pendingDates());
        $this->signal()->flush();

        // Lunas dari Midtrans (fulfillment).
        $order = Order::create(['order_number' => 'ORD-RT-1', 'user_id' => $this->customer->id, 'order_type' => 'ONLINE_BOOKING', 'subtotal' => 200000, 'grand_total' => 200000, 'payment_status' => 'PAID']);
        $unpaid = $this->booking('PENDING_PAYMENT', Carbon::parse('2026-10-26 18:00'), ['order_id' => $order->id]);
        app(PadelFulfillmentHandler::class)->fulfill($order, collect());
        $this->assertSame('PAID', $unpaid->fresh()->status);
        $this->assertSame(['2026-10-26'], $this->signal()->pendingDates());
        $this->signal()->flush();

        // Refund diproses / ditolak → booking REFUNDED / CANCELLED.
        $pendingRefund = $this->booking('REFUND_PENDING', Carbon::parse('2026-10-27 18:00'));
        $refund = new Refund(['padel_booking_id' => $pendingRefund->id]);
        (new \ReflectionMethod(RefundQueueService::class, 'closeBooking'))->invoke(app(RefundQueueService::class), $refund, 'REFUNDED');
        $this->assertSame('REFUNDED', $pendingRefund->fresh()->status);
        $this->assertSame(['2026-10-27'], $this->signal()->pendingDates());
    }

    public function test_check_in_at_the_gate_signals(): void
    {
        $start = now()->addMinutes(10)->startOfMinute();
        $booking = $this->booking('PAID', $start, ['qr_code_hash' => 'hash-rt-1']);

        app(PadelBookingService::class)->checkIn('hash-rt-1', User::factory()->superAdmin()->create());

        $this->assertSame('CHECKED_IN', $booking->fresh()->status);
        $this->assertSame([$start->format('Y-m-d')], $this->signal()->pendingDates());
    }

    public function test_reverb_down_never_breaks_the_request_and_warns_once(): void
    {
        $this->enableRealtime();
        config(['broadcasting.connections.reverb.options.host' => '127.0.0.1', 'broadcasting.connections.reverb.options.port' => 1]);
        Log::spy();

        $this->booking('LOCKED', Carbon::parse('2026-10-16 19:00'));   // flush → Reverb tidak terjangkau

        // 10 dtk berikutnya tidak dicoba lagi → request tidak ikut menunggu koneksi yang pasti gagal.
        $started = microtime(true);
        $this->booking('LOCKED', Carbon::parse('2026-10-16 20:00'));
        $this->assertLessThan(0.5, microtime(true) - $started);

        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message) => str_contains($message, 'Reverb'));
        $this->assertSame(2, PadelBooking::count(), 'transaksinya tetap tersimpan');

        // Sesudah jeda, dicoba lagi (Reverb mungkin sudah hidup).
        Event::fake([BookingBoardChanged::class]);
        $this->travel(11)->seconds();
        $this->booking('LOCKED', Carbon::parse('2026-10-16 21:00'));
        Event::assertDispatchedTimes(BookingBoardChanged::class, 1);
    }

    public function test_nothing_is_broadcast_when_realtime_is_off(): void
    {
        Event::fake([BookingBoardChanged::class]);
        $this->booking('LOCKED', Carbon::parse('2026-10-16 19:00'));

        Event::assertNotDispatched(BookingBoardChanged::class);
    }

    public function test_signage_publish_and_tv_heartbeat_signal(): void
    {
        $this->enableRealtime();
        Event::fake([SignatureChanged::class]);
        $admin = User::factory()->superAdmin()->create();
        Club61PermissionMatrix::syncAllPermissions('web');
        $screen = app(SignatureService::class)->ensureDefaults();

        app(SignatureService::class)->publish($screen->fresh(), $admin);
        app(SignatureService::class)->publish($screen->fresh(), $admin); // tidak ada perubahan → tidak ada sinyal
        $this->signal()->flush();
        Event::assertDispatchedTimes(SignatureChanged::class, 1);
        Event::assertDispatched(SignatureChanged::class, fn ($e) => $e->broadcastWith() === ['kind' => 'published']);

        SignatureTemplate::find($screen->template_id)->update(['name' => 'Baru']);
        app(SignatureService::class)->publish($screen->fresh(), $admin);
        $this->signal()->flush();
        Event::assertDispatchedTimes(SignatureChanged::class, 2);

        // TV melapor hidup: sinyal "seen" (tanpa token layar), paling sering sekali per 50 dtk.
        $this->getJson("/signature/{$screen->token}/live")->assertOk();
        $this->getJson("/signature/{$screen->token}/live")->assertOk();
        Event::assertDispatched(SignatureChanged::class, fn ($e) => $e->kind === 'seen' && $e->screenId === $screen->id && ! in_array($screen->token, $e->broadcastWith(), true));
        Event::assertDispatchedTimes(SignatureChanged::class, 3);
    }

    public function test_reverb_without_key_falls_back_to_polling_instead_of_crashing_the_app(): void
    {
        $read = function (array $env) {
            $backup = [];
            foreach ($env as $name => $value) {
                $backup[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];
                $_ENV[$name] = $_SERVER[$name] = $value;
                putenv("{$name}={$value}");
            }
            try {
                return (require config_path('broadcasting.php'))['default'];
            } finally {
                foreach ($backup as $name => [$env, $server, $put]) {
                    if ($env === null) { unset($_ENV[$name]); } else { $_ENV[$name] = $env; }
                    if ($server === null) { unset($_SERVER[$name]); } else { $_SERVER[$name] = $server; }
                    putenv($put === false ? $name : "{$name}={$put}");
                }
            }
        };

        $this->assertSame('null', $read(['BROADCAST_CONNECTION' => 'reverb', 'REVERB_APP_KEY' => '']));
        $this->assertSame('reverb', $read(['BROADCAST_CONNECTION' => 'reverb', 'REVERB_APP_KEY' => 'abc']));
        $this->assertSame('log', $read(['BROADCAST_CONNECTION' => 'log', 'REVERB_APP_KEY' => '']));
    }

    public function test_private_user_channel_only_admits_its_owner(): void
    {
        $owner = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $callback = Broadcast::driver()->getChannels()->get('App.Models.User.{id}');

        $this->assertTrue($callback($owner, (string) $owner->id));
        $this->assertFalse($callback($other, (string) $owner->id));
    }
}
