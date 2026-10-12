<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Services\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, UserNotificationService $notifications): JsonResponse
    {
        return response()->json([
            'notifications' => $notifications->unreadForFrontend($request->user()),
        ]);
    }

    public function dismiss(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }
}
