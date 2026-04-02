<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegisterByInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_invite_link(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson($this->api('admin/invites'), [
            'email' => 'novo.agente@example.com',
            'expires_in_hours' => 48,
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'id',
                'invite_url',
                'invite_token',
                'email',
                'expires_at',
            ])
            ->assertJsonFragment([
                'email' => 'novo.agente@example.com',
            ]);

        $this->assertDatabaseHas('user_invites', [
            'email' => 'novo.agente@example.com',
            'created_by' => $admin->id,
        ]);
    }

    public function test_user_can_register_with_valid_invite(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $plainToken = 'invite-token-abc-123';

        $invite = UserInvite::create([
            'email' => 'convite@example.com',
            'role' => 'agent',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
            'created_by' => $admin->id,
        ]);

        $response = $this->postJson($this->api('auth/register'), [
            'name' => 'Agente Convidado',
            'email' => 'convite@example.com',
            'phone_e164' => '+5511999999999',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'invite_token' => $plainToken,
        ]);

        $response
            ->assertCreated()
            ->assertJsonFragment([
                'message' => 'Cadastro realizado com sucesso! Sua conta já está ativa.',
                'email' => 'convite@example.com',
                'role' => 'agent',
            ]);

        $createdUser = User::query()->where('email', 'convite@example.com')->firstOrFail();

        $this->assertTrue($createdUser->is_approved);
        $this->assertSame($admin->id, $createdUser->approved_by);

        $invite->refresh();
        $this->assertNotNull($invite->used_at);
        $this->assertSame($createdUser->id, $invite->used_by);
    }

    public function test_register_fails_with_invalid_or_expired_invite(): void
    {
        UserInvite::create([
            'email' => 'expirado@example.com',
            'role' => 'agent',
            'token_hash' => hash('sha256', 'expired-token'),
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->postJson($this->api('auth/register'), [
            'name' => 'Agente Expirado',
            'email' => 'expirado@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'invite_token' => 'expired-token',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('invite_token');
    }

    private function api(string $path): string
    {
        $version = config('app.api_version');

        return sprintf('/api/%s/%s', $version, ltrim($path, '/'));
    }
}
