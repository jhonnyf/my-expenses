<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->limit(20)->get();

        return $this->success([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $model = $request->user()->notifications()->where('id', $notification)->firstOrFail();
        $model->markAsRead();

        return $this->success(['success' => true]);
    }
}
