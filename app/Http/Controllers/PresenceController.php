<?php

namespace App\Http\Controllers;

use App\Services\UserPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function heartbeat(Request $request, UserPresenceService $presence): JsonResponse
    {
        $stats = $presence->heartbeat($request->user());

        return response()->json([
            'success' => true,
            ...$stats,
        ]);
    }
}
