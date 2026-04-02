<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserInviteController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email'],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        $plainToken = Str::random(64);
        $expiresAt = now()->addHours($validated['expires_in_hours'] ?? 168);

        $invite = UserInvite::create([
            'email' => $validated['email'] ?? null,
            'role' => 'agent',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
            'created_by' => $request->user()->id,
        ]);

        $baseRegisterUrl = rtrim(config('app.url').'/register', '/');
        $query = ['invite_token' => $plainToken];

        if (! empty($validated['email'])) {
            $query['email'] = $validated['email'];
        }

        return response()->json([
            'id' => $invite->id,
            'invite_url' => $baseRegisterUrl.'?'.http_build_query($query),
            'invite_token' => $plainToken,
            'email' => $invite->email,
            'expires_at' => $invite->expires_at,
        ], 201);
    }
}
