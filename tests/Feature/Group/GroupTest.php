<?php

namespace Tests\Feature\Group;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_groups_they_belong_to(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $groups = Group::factory()->count(2)->create(['owner_id' => $user->id]);

        foreach ($groups as $group) {
            $group->members()->attach($user->id, ['role_in_group' => 'owner']);
        }

        $foreignGroup = Group::factory()->create(['owner_id' => $other->id]);
        $foreignGroup->members()->attach($other->id, ['role_in_group' => 'owner']);

        $response = $this->actingAs($user)
            ->getJson($this->api('groups'));

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'name' => $groups[0]->name,
            ]);
    }

    public function test_user_can_create_group_and_become_owner(): void
    {
        $user = User::factory()->create();

        $payload = [
            'name' => 'Equipe Dehon',
        ];

        $response = $this->actingAs($user)
            ->postJson($this->api('groups'), $payload);

        $response
            ->assertCreated()
            ->assertJsonFragment([
                'name' => 'Equipe Dehon',
                'owner_id' => $user->id,
            ]);

        $groupId = $response->json('data.id');

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

    public function test_non_owner_cannot_update_group(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $group = Group::factory()->create(['owner_id' => $owner->id]);
        $group->members()->attach($owner->id, ['role_in_group' => 'owner']);
        $group->members()->attach($member->id, ['role_in_group' => 'member']);

        $response = $this->actingAs($member)
            ->patchJson($this->api("groups/{$group->id}"), [
                'name' => 'Novo Nome',
            ]);

        $response->assertForbidden();
    }

    public function test_owner_can_add_member_and_change_role(): void
    {
        $owner = User::factory()->create();
        $newAgent = User::factory()->create();

        $group = Group::factory()->create(['owner_id' => $owner->id]);
        $group->members()->attach($owner->id, ['role_in_group' => 'owner']);

        $this->actingAs($owner)
            ->postJson($this->api("groups/{$group->id}/members"), [
                'user_id' => $newAgent->id,
                'role_in_group' => 'member',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('group_user', [
            'group_id' => $group->id,
            'user_id' => $newAgent->id,
            'role_in_group' => 'member',
        ]);

        $this->actingAs($owner)
            ->patchJson($this->api("groups/{$group->id}/members/{$newAgent->id}"), [
                'role_in_group' => 'moderator',
            ])
            ->assertOk()
            ->assertJsonFragment([
                'role_in_group' => 'moderator',
            ]);

        $this->assertDatabaseHas('group_user', [
            'group_id' => $group->id,
            'user_id' => $newAgent->id,
            'role_in_group' => 'moderator',
        ]);
    }

    public function test_owner_cannot_be_removed_from_group(): void
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create(['owner_id' => $owner->id]);
        $group->members()->attach($owner->id, ['role_in_group' => 'owner']);

        $this->actingAs($owner)
            ->deleteJson($this->api("groups/{$group->id}/members/{$owner->id}"))
            ->assertStatus(422);
    }

    protected function api(string $path): string
    {
        $version = config('app.api_version');

        return sprintf('/api/%s/%s', $version, ltrim($path, '/'));
    }
}
