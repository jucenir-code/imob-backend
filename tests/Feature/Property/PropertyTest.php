<?php

namespace Tests\Feature\Property;

use App\Models\Group;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_properties_of_group_they_belong_to(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['owner_id' => $user->id]);

        $group->members()->syncWithoutDetaching([$user->id => ['role_in_group' => 'owner']]);

        Property::factory()->count(2)->create(['group_id' => $group->id, 'owner_id' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson($this->api('properties?group_id='.$group->id));

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_cannot_list_properties_of_other_group(): void
    {
        $user = User::factory()->create();
        $foreignGroup = Group::factory()->create();

        Property::factory()->create(['group_id' => $foreignGroup->id, 'owner_id' => $foreignGroup->owner_id]);

        $this->actingAs($user)
            ->getJson($this->api('properties?group_id='.$foreignGroup->id))
            ->assertForbidden();
    }

    public function test_member_can_create_property_in_group(): void
    {
        $user = User::factory()->create(['is_approved' => true]);
        $group = Group::factory()->create(['owner_id' => $user->id]);
        $group->members()->syncWithoutDetaching([$user->id => ['role_in_group' => 'owner']]);

        $payload = [
            'group_id' => $group->id,
            'type' => 'house',
            'title' => 'Casa de Campo',
            'description' => 'Linda casa com área verde.',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'parking' => 2,
            'area_m2' => 180,
            'neighborhood' => 'Dehon',
            'city' => 'Tubarao',
            'state' => 'SC',
            'price' => 750000,
            'price_visibility' => 'show',
            'status' => 'active',
        ];

        $response = $this->actingAs($user)
            ->postJson($this->api('properties'), $payload);

        $response
            ->assertCreated()
            ->assertJsonFragment([
                'title' => 'Casa de Campo',
                'group_id' => $group->id,
            ]);

        $this->assertDatabaseHas('properties', [
            'group_id' => $group->id,
            'title' => 'Casa de Campo',
            'owner_id' => $user->id,
        ]);
    }

    public function test_user_cannot_create_property_in_group_they_do_not_belong_to(): void
    {
        $user = User::factory()->create(['is_approved' => true]);
        $group = Group::factory()->create();

        $payload = [
            'group_id' => $group->id,
            'type' => 'house',
            'title' => 'Casa de Praia',
            'description' => 'Frente mar.',
            'neighborhood' => 'Centro',
            'city' => 'Imbituba',
            'state' => 'SC',
        ];

        $this->actingAs($user)
            ->postJson($this->api('properties'), $payload)
            ->assertForbidden();
    }

    public function test_only_owner_or_moderator_can_update_property(): void
    {
        $owner = User::factory()->create(['is_approved' => true]);
        $moderator = User::factory()->create(['is_approved' => true]);
        $member = User::factory()->create(['is_approved' => true]);

        $group = Group::factory()->create(['owner_id' => $owner->id]);
        $group->members()->syncWithoutDetaching([
            $owner->id => ['role_in_group' => 'owner'],
            $moderator->id => ['role_in_group' => 'moderator'],
            $member->id => ['role_in_group' => 'member'],
        ]);

        $property = Property::factory()->create([
            'group_id' => $group->id,
            'owner_id' => $owner->id,
            'title' => 'Casa Azul',
        ]);

        $this->actingAs($moderator)
            ->patchJson($this->api("properties/{$property->id}"), [
                'title' => 'Casa Azul Reformada',
            ])
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Casa Azul Reformada',
            ]);

        $this->actingAs($member)
            ->patchJson($this->api("properties/{$property->id}"), [
                'title' => 'Tentativa',
            ])
            ->assertForbidden();
    }

    public function test_approved_user_can_create_property_without_group_and_group_is_created_automatically(): void
    {
        $user = User::factory()->create(['is_approved' => true]);

        $payload = [
            'type' => 'house',
            'title' => 'Casa sem grupo',
            'description' => 'Cadastro sem selecionar grupo.',
            'neighborhood' => 'Centro',
            'city' => 'Tubarao',
            'state' => 'SC',
        ];

        $response = $this->actingAs($user)
            ->postJson($this->api('properties'), $payload);

        $response
            ->assertCreated()
            ->assertJsonFragment([
                'title' => 'Casa sem grupo',
            ]);

        $groupId = $response->json('data.group_id');

        $this->assertNotNull($groupId);
        $this->assertDatabaseHas('groups', [
            'id' => $groupId,
            'owner_id' => $user->id,
        ]);
        $this->assertDatabaseHas('group_user', [
            'group_id' => $groupId,
            'user_id' => $user->id,
            'role_in_group' => 'owner',
        ]);
    }

    protected function api(string $path): string
    {
        $version = config('app.api_version');

        return sprintf('/api/%s/%s', $version, ltrim($path, '/'));
    }
}
