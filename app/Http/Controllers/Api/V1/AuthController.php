<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UpdateUserLocationAction;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return $this->error(__('auth.failed'), 401);
        }

        $user = Auth::user();
        $token = $user->createToken($request->input('device_name', 'api'))->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function register(RegisterRequest $request, UpdateUserLocationAction $locationAction): JsonResponse
    {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        $locationAction->execute($user, $request->input('cidade'), $request->input('estado'));

        $token = $user->createToken($request->input('device_name', 'api'))->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => new UserResource($user),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();
        $token->delete();

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }
}
