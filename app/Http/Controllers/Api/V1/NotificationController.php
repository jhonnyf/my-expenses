<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private const LIST_LIMIT = 20;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => NotificationResource::collection($user->notifications()->latest()->limit(self::LIST_LIMIT)->get()),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->where('id', $notification)->firstOrFail()->markAsRead();

        return $this->success(['success' => true]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(['success' => true]);
    }
}
