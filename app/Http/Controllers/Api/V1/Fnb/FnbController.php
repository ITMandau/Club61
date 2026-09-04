<?php

namespace App\Http\Controllers\Api\V1\Fnb;

use App\Http\Controllers\Controller;
use App\Models\Fnb\FnbCategory;
use App\Models\Fnb\FnbMenu;
use Illuminate\Http\JsonResponse;

class FnbController extends Controller
{
    public function menu(): JsonResponse
    {
        $categories = FnbCategory::with(['menus' => function ($q) {
            $q->where('is_available', true);
        }])->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar menu cafe & F&B Club 61.',
            'data' => $categories,
        ]);
    }
}
