<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class UtilityController extends Controller
{
    public function nigerianStates(): JsonResponse
    {
        return response()->json([
            'data' => config('nigeria.states'),
        ]);
    }
}
