<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_token(): void
    {
        User::factory()->create([
            'email' => 'agent@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson($this->api('auth/login'), [
            'email' => 'agent@example.com',
            'password' => 'secret123',
            'device_name' => 'ios-app',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'phone_e164',
                    'role',
                    'status',
                    'groups',
                ],
            ]);

        $token = $response->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($this->api('auth/me'))
            ->assertOk()
            ->assertJsonFragment([
                'email' => 'agent@example.com',
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'agent@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson($this->api('auth/login'), [
            'email' => 'agent@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_blocks_inactive_users(): void
    {
        User::factory()->create([
            'email' => 'agent@example.com',
            'password' => Hash::make('secret123'),
            'status' => 'suspended',
        ]);

        $response = $this->postJson($this->api('auth/login'), [
            'email' => 'agent@example.com',
            'password' => 'secret123',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => trans('auth.account_inactive'),
            ]);
    }

    public function test_user_can_logout_revoking_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson($this->api('auth/logout'))
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    private function api(string $path): string
    {
        $version = config('app.api_version');

        return sprintf('/api/%s/%s', $version, ltrim($path, '/'));
    }
}
