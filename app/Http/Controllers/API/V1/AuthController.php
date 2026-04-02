<?php

namespace App\Http\Controllers\API\V1;

use App\Actions\Auth\RegisterUserFromInvite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterUserFromInvite $registerUserFromInvite
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('email', $request->validated('email'))
            ->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => trans('auth.account_inactive'),
            ], 403);
        }

        $deviceName = $request->validated('device_name') ?: $request->header('User-Agent', 'mobile-app');

        $token = $user->createToken($deviceName, abilities: ['*'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('groups')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([], 204);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('groups'));
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate(RegisterUserFromInvite::rules());

        $user = $this->registerUserFromInvite->handle($validated);

        return response()->json([
            'message' => 'Cadastro realizado com sucesso! Sua conta já está ativa.',
            'user' => new UserResource($user),
        ], 201);
    }
}
