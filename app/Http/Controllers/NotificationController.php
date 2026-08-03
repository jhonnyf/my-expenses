<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $notifications = Auth::user()->notifications()->latest()->limit(20)->get();

        return response()->json([
            'unread_count' => Auth::user()->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(string $notification): JsonResponse
    {
        $model = Auth::user()->notifications()->where('id', $notification)->firstOrFail();
        $model->markAsRead();

        return response()->json(['success' => true]);
    }
}
