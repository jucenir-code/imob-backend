<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\RegisterPushTokenRequest;
use App\Models\UserPushToken;
use Illuminate\Http\JsonResponse;

class PushTokenController extends Controller
{
    public function store(RegisterPushTokenRequest $request): JsonResponse
    {
        $token = UserPushToken::query()->updateOrCreate(
            ['token' => $request->validated('token')],
            [
                'user_id' => $request->user()->id,
                'platform' => $request->validated('platform'),
                'device_name' => $request->validated('device_name'),
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'data' => [
                'id' => $token->id,
                'token' => $token->token,
            ],
        ], 201);
    }
}
