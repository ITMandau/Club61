<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\Drivers\MidtransDriver;
use App\Services\Payment\Drivers\MockSimulatorDriver;
use App\Services\Payment\Drivers\XenditDriver;
use InvalidArgumentException;

class PaymentManager
{
    protected array $drivers = [];

    /**
     * Dapatkan instance driver pembayaran (midtrans, xendit, atau mock).
     */
    public function driver(?string $name = null): PaymentGatewayInterface
    {
        $name = strtolower($name ?: config('services.payment.driver', 'midtrans'));

        if (! isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Factory untuk membuat driver sesuai nama.
     */
    protected function createDriver(string $name): PaymentGatewayInterface
    {
        return match ($name) {
            'midtrans' => app(MidtransDriver::class),
            'xendit' => app(XenditDriver::class),
            'mock' => app(MockSimulatorDriver::class),
            default => throw new InvalidArgumentException("Payment gateway driver [{$name}] tidak didukung. Pilih: midtrans, xendit, atau mock."),
        };
    }

    /**
     * Helper langsung untuk createPayment via default driver.
     */
    public function createPayment(array $params): array
    {
        return $this->driver()->createPayment($params);
    }

    /**
     * Dapatkan nama driver aktif saat ini.
     */
    public function getDefaultDriver(): string
    {
        return strtolower(config('services.payment.driver', 'midtrans'));
    }
}
