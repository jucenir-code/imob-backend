<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PropertyViewsTest extends TestCase
{
    use RefreshDatabase;

    private function property(): Property
    {
        Queue::fake();
        $owner = User::factory()->create();
        $group = Group::create(['name' => 'Carteira', 'visibility' => 'private', 'owner_id' => $owner->id]);

        return Property::create([
            'group_id' => $group->id, 'owner_id' => $owner->id, 'type' => 'apartment',
            'title' => 'Apartamento', 'slug' => 'apartamento', 'description' => 'Descrição',
            'neighborhood' => 'Mar Grosso', 'city' => 'Laguna', 'state' => 'SC', 'status' => 'active',
        ]);
    }

    public function test_views_count_once_per_user_per_day_across_web_and_api(): void
    {
        $property = $this->property();
        $viewer = User::factory()->create();
        $this->actingAs($viewer, 'web');
        $this->travelTo(now()->startOfDay()->addHours(12));
        $this->postJson("/web/properties/{$property->id}/views")->assertOk()->assertJsonPath('views_count', 1);
        $this->postJson("/web/properties/{$property->id}/views")->assertOk()->assertJsonPath('views_count', 1);
        $this->postJson('/api/'.config('app.api_version')."/properties/{$property->id}/views")
            ->assertOk()->assertJsonPath('views_count', 1);
        $this->assertDatabaseCount('property_views', 1);
        $this->assertEquals($property->updated_at, $property->fresh()->updated_at);
        $this->actingAs(User::factory()->create(), 'web');
        $this->postJson("/web/properties/{$property->id}/views")->assertOk()->assertJsonPath('views_count', 2);
        $this->actingAs($viewer, 'web');
        $this->travel(1)->days();
        $this->postJson("/web/properties/{$property->id}/views")->assertOk()->assertJsonPath('views_count', 3);
        $this->assertDatabaseCount('property_views', 3);
        $this->getJson('/web/properties')->assertOk()->assertJsonPath('data.0.views_count', 3);
        $this->travelBack();
    }

    public function test_list_detail_and_owner_visits_do_not_inflate_the_counter(): void
    {
        $property = $this->property();
        $this->actingAs(User::factory()->create(), 'web');
        $this->getJson('/web/properties')->assertOk()->assertJsonPath('data.0.views_count', 0);
        $this->getJson("/web/properties/{$property->id}")->assertOk()->assertJsonPath('data.views_count', 0);
        $this->actingAs($property->owner, 'web');
        $this->postJson("/web/properties/{$property->id}/views")->assertOk()->assertJsonPath('views_count', 0);
        $this->assertDatabaseCount('property_views', 0);
        $this->assertSame(0, $property->fresh()->views_count);
    }

    public function test_unauthorized_inactive_draft_and_deleted_visits_are_not_counted(): void
    {
        $property = $this->property();
        $url = "/web/properties/{$property->id}/views";
        $this->postJson($url)->assertUnauthorized();
        $user = User::factory()->create(['status' => 'suspended']);
        $this->actingAs($user, 'web')->postJson($url)->assertForbidden();
        $user->update(['status' => 'active']);
        Gate::before(fn ($viewer, $ability) => $ability === 'view' && $viewer->id === $user->id ? false : null);
        $this->postJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(), 'web');
        $property->update(['status' => 'draft']);
        $this->postJson($url)->assertOk()->assertJsonPath('views_count', 0);
        $property->delete();
        $this->postJson($url)->assertNotFound();
        $this->assertDatabaseCount('property_views', 0);
    }

    public function test_recording_a_view_requires_csrf_and_ignores_client_supplied_counts(): void
    {
        $property = $this->property();
        $this->actingAs(User::factory()->create(), 'web');
        $url = "/web/properties/{$property->id}/views";
        $this->postJson($url, ['views_count' => 9000, 'user_id' => $property->owner_id])
            ->assertOk()->assertJsonPath('views_count', 1);
        $this->assertSame(1, $property->fresh()->views_count);
        $this->app->bind(\App\Http\Middleware\VerifyCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends \App\Http\Middleware\VerifyCsrfToken
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });
        $this->postJson($url)->assertStatus(419);
    }
}
