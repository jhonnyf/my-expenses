<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\FindOrCreateSocialUser;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SocialAuthController extends Controller
{
    private const ALLOWED_PROVIDERS = ['google', 'facebook', 'apple'];

    public function login(Request $request, string $provider, FindOrCreateSocialUser $action): JsonResponse
    {
        if (! in_array($provider, self::ALLOWED_PROVIDERS, strict: true)) {
            throw new NotFoundHttpException();
        }

        $request->validate([
            'token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->userFromToken($request->input('token'));
            $user = $action->handle($socialUser, $provider);

            $token = $user->createToken($request->input('device_name', $provider))->plainTextToken;

            return $this->success([
                'token' => $token,
                'user' => new UserResource($user),
            ]);
        } catch (\Throwable $e) {
            Log::error("Social API login error [{$provider}]: " . $e->getMessage());

            return $this->error(__('auth.social_failed'), 422);
        }
    }
}
