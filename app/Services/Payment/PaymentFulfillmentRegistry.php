<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\DomainFulfillmentHandlerInterface;
use InvalidArgumentException;

class PaymentFulfillmentRegistry
{
    /**
     * @var array<string, DomainFulfillmentHandlerInterface|class-string<DomainFulfillmentHandlerInterface>>
     */
    protected array $handlers = [];

    /**
     * Mendaftarkan handler untuk tipe item tertentu.
     */
    public function register(string $itemType, string|DomainFulfillmentHandlerInterface $handler): void
    {
        $this->handlers[strtoupper(trim($itemType))] = $handler;
    }

    /**
     * Memeriksa apakah handler untuk item type tertentu terdaftar.
     */
    public function hasHandler(string $itemType): bool
    {
        return isset($this->handlers[strtoupper(trim($itemType))]);
    }

    /**
     * Mendapatkan handler yang telah di-resolve dari service container.
     */
    public function getHandler(string $itemType): ?DomainFulfillmentHandlerInterface
    {
        $key = strtoupper(trim($itemType));

        if (! isset($this->handlers[$key])) {
            return null;
        }

        $handler = $this->handlers[$key];

        if (is_string($handler)) {
            $resolved = app($handler);

            if (! $resolved instanceof DomainFulfillmentHandlerInterface) {
                throw new InvalidArgumentException("Handler [{$handler}] wajib mengimplementasikan DomainFulfillmentHandlerInterface.");
            }

            return $resolved;
        }

        return $handler;
    }
}
