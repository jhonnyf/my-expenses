<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\SubscriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /** Plano atual e o que cada plano inclui (mesma fonte da página web de planos). */
    public function plans(Request $request): JsonResponse
    {
        $user = $request->user()->load('subscription');

        return $this->success([
            'is_pro' => $user->isPro(),
            'subscription' => $user->subscription ? new SubscriptionResource($user->subscription) : null,
            'features' => collect(config('plans.features'))
                ->map(fn (array $feature, string $key) => ['key' => $key, 'label' => $feature['label'], 'free' => $feature['free']])
                ->values(),
        ]);
    }
}
