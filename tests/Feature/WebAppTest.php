<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_shell_and_protected_routes_use_native_sessions(): void
    {
        $this->withoutVite();
        $this->get('/')->assertRedirect('/app/imoveis');
        $this->get('/login')->assertOk()->assertSee('manifest.webmanifest');
        $this->get('/app/imoveis')->assertRedirect('/login');
        $this->getJson('/web/properties')->assertUnauthorized();

        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertOk()->assertJsonMissingPath('token')->assertJsonFragment(['email' => $user->email]);
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->get('/app/imoveis/123')->assertOk()->assertSee('id="app"', false);
        $this->getJson('/web/profile')->assertOk()->assertJsonFragment(['email' => $user->email]);
        $this->getJson('/web/properties')->assertOk()->assertJsonStructure(['data', 'meta']);
        $this->postJson('/logout')->assertNoContent();
        $this->assertGuest('web');
        $this->getJson('/web/properties')->assertUnauthorized();
    }

    public function test_web_login_rejects_invalid_password_and_inactive_accounts(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123'), 'status' => 'suspended']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'secret123'])->assertUnprocessable();
        $this->assertGuest('web');
        $user->update(['status' => 'active']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertUnprocessable();
        $this->assertGuest('web');
    }

    public function test_web_routes_keep_existing_approval_and_admin_permissions(): void
    {
        $user = User::factory()->create(['role' => 'agent', 'is_approved' => false]);
        $this->actingAs($user, 'web');
        $this->postJson('/web/properties', [])->assertForbidden();
        $this->getJson('/web/admin/users')->assertForbidden();
        $this->postJson('/web/admin/invites', [])->assertForbidden();
    }

    public function test_web_and_mobile_use_the_same_group_data_and_policies(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $this->actingAs($owner, 'web');
        $created = $this->postJson('/web/groups', ['name' => 'Parceiros web'])->assertCreated();
        $id = $created->json('data.id') ?? $created->json('id');
        $this->getJson('/api/'.config('app.api_version').'/groups/'.$id)->assertOk()->assertJsonFragment(['name' => 'Parceiros web']);
        $this->actingAs($stranger, 'web');
        $this->getJson('/web/groups/'.$id)->assertForbidden();
        $this->putJson('/web/groups/'.$id, ['name' => 'Alterado'])->assertForbidden();
    }

    public function test_web_mutations_are_csrf_protected(): void
    {
        // Laravel normally bypasses CSRF in tests; bind a version that executes it.
        $this->app->bind(\App\Http\Middleware\VerifyCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends \App\Http\Middleware\VerifyCsrfToken
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });
        $this->postJson('/login', ['email' => 'a@example.com', 'password' => 'secret123'])->assertStatus(419);
        $this->actingAs(User::factory()->create(), 'web');
        $this->postJson('/web/groups', ['name' => 'Sem CSRF'])->assertStatus(419);
    }
}
