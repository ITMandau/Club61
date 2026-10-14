<?php

namespace App\Events;

use App\Support\Realtime;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Digital Signage: "published" = tampilan baru ditayangkan / versi dikembalikan (TV langsung memuat ulang);
 * "seen" = TV melapor masih hidup (status Online di admin). Tanpa token layar.
 */
class SignatureChanged implements ShouldBroadcastNow
{
    public function __construct(
        public readonly string $kind,
        public readonly ?string $screenId = null,
        public readonly ?string $seenAt = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel(Realtime::SIGNATURE_CHANNEL);
    }

    public function broadcastAs(): string
    {
        return Realtime::SIGNATURE_EVENT;
    }

    public function broadcastWith(): array
    {
        return array_filter(['kind' => $this->kind, 'screen_id' => $this->screenId, 'seen_at' => $this->seenAt], fn ($v) => $v !== null);
    }
}
