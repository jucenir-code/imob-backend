<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterUserFromInvite
{
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone_e164' => ['nullable', 'string', 'max:20', 'unique:users,phone_e164'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'invite_token' => ['required', 'string'],
        ];
    }

    public function findValidInvite(string $plainToken): ?UserInvite
    {
        return UserInvite::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * @param array{
     *   name:string,
     *   email:string,
     *   phone_e164?:string|null,
     *   password:string,
     *   invite_token:string
     * } $validated
     */
    public function handle(array $validated): User
    {
        $invite = $this->findValidInvite($validated['invite_token']);

        if (! $invite) {
            throw ValidationException::withMessages([
                'invite_token' => 'Link de convite inválido ou expirado.',
            ]);
        }

        if (! empty($invite->email) && strcasecmp($invite->email, $validated['email']) !== 0) {
            throw ValidationException::withMessages([
                'email' => 'Este convite foi emitido para outro e-mail.',
            ]);
        }

        return DB::transaction(function () use ($validated, $invite) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone_e164' => $validated['phone_e164'] ?? null,
                'password' => $validated['password'],
                'role' => $invite->role,
                'status' => 'active',
                'is_approved' => true,
                'approved_at' => now(),
                'approved_by' => $invite->created_by,
            ]);

            $invite->update([
                'used_at' => now(),
                'used_by' => $user->id,
            ]);

            return $user;
        });
    }
}
