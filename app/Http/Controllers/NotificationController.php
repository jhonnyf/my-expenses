<?php

namespace App\Http\Controllers;

use App\Http\Resources\Api\V1\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    private const LIST_LIMIT = 20;

    public function index(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => NotificationResource::collection($user->notifications()->latest()->limit(self::LIST_LIMIT)->get()),
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json(['unread_count' => Auth::user()->unreadNotifications()->count()]);
    }

    public function markAsRead(string $notification): JsonResponse
    {
        Auth::user()->notifications()->where('id', $notification)->firstOrFail()->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(): JsonResponse
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
