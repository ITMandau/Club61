<?php

namespace App\Http\Controllers\Api\V1\Merch;

use App\Http\Controllers\Controller;
use App\Models\Merch\MerchProduct;
use Illuminate\Http\JsonResponse;

class MerchController extends Controller
{
    public function index(): JsonResponse
    {
        $products = MerchProduct::with('variants')->where('is_active', true)->get();
        return response()->json([
            'success' => true,
            'message' => 'Daftar merchandise resmi Club 61.',
            'data' => $products,
        ]);
    }
}
