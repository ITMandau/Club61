<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlotConflictException extends Exception
{
    protected ?string $courtName;
    protected ?string $timeRange;

    public function __construct(string $message = 'Slot lapangan pada jam tersebut sudah dipesan atau sedang di-hold pemain lain.', ?string $courtName = null, ?string $timeRange = null)
    {
        parent::__construct($message, 409);
        $this->courtName = $courtName;
        $this->timeRange = $timeRange;
    }

    public function getCourtName(): ?string
    {
        return $this->courtName;
    }

    public function getTimeRange(): ?string
    {
        return $this->timeRange;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => [
                'code' => 'SLOT_CONFLICT',
                'court' => $this->courtName,
                'time' => $this->timeRange,
            ],
        ], 409);
    }
}
