<?php

namespace Tests\Feature\Deal;

use App\Models\Deal;
use App\Models\Group;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_create_deal_for_property(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $group = Group::factory()->create(['owner_id' => $seller->id]);
        $group->members()->syncWithoutDetaching([
            $seller->id => ['role_in_group' => 'owner'],
            $buyer->id => ['role_in_group' => 'member'],
        ]);

        $property = Property::factory()->create([
            'group_id' => $group->id,
            'owner_id' => $seller->id,
        ]);

        $payload = [
            'property_id' => $property->id,
            'buyer_name' => 'Cliente X',
            'buyer_contact' => '+5548991111111',
        ];

        $response = $this->actingAs($buyer)
            ->postJson($this->api('deals'), $payload);

        $response
            ->assertCreated()
            ->assertJsonFragment([
                'property_id' => $property->id,
                'buyer_name' => 'Cliente X',
                'status' => 'initiated',
            ]);

        $this->assertDatabaseHas('deals', [
            'property_id' => $property->id,
            'buyer_agent_id' => $buyer->id,
            'seller_agent_id' => $seller->id,
        ]);
    }

    public function test_member_reuses_existing_open_deal_for_same_property(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $group = Group::factory()->create(['owner_id' => $seller->id]);
        $group->members()->syncWithoutDetaching([
            $seller->id => ['role_in_group' => 'owner'],
            $buyer->id => ['role_in_group' => 'member'],
        ]);

        $property = Property::factory()->create([
            'group_id' => $group->id,
            'owner_id' => $seller->id,
        ]);

        $first = $this->actingAs($buyer)
            ->postJson($this->api('deals'), [
                'property_id' => $property->id,
            ])
            ->assertCreated();

        $firstDealId = $first->json('data.id') ?? $first->json('id');

        $second = $this->actingAs($buyer)
            ->postJson($this->api('deals'), [
                'property_id' => $property->id,
            ]);

        $second
            ->assertOk()
            ->assertJsonFragment([
                'id' => $firstDealId,
            ]);

        $this->assertDatabaseCount('deals', 1);
    }

    public function test_user_cannot_create_deal_if_not_group_member(): void
    {
        $seller = User::factory()->create();
        $outsider = User::factory()->create();

        $group = Group::factory()->create(['owner_id' => $seller->id]);
        $group->members()->syncWithoutDetaching([$seller->id => ['role_in_group' => 'owner']]);

        $property = Property::factory()->create([
            'group_id' => $group->id,
            'owner_id' => $seller->id,
        ]);

        $this->actingAs($outsider)
            ->postJson($this->api('deals'), [
                'property_id' => $property->id,
            ])
            ->assertForbidden();
    }

    public function test_participants_can_list_deals(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $group = Group::factory()->create(['owner_id' => $seller->id]);
        $group->members()->syncWithoutDetaching([
            $seller->id => ['role_in_group' => 'owner'],
            $buyer->id => ['role_in_group' => 'member'],
        ]);

        $property = Property::factory()->create([
            'group_id' => $group->id,
            'owner_id' => $seller->id,
        ]);

        Deal::factory()->create([
            'property_id' => $property->id,
            'seller_agent_id' => $seller->id,
            'buyer_agent_id' => $buyer->id,
        ]);

        $this->actingAs($buyer)
            ->getJson($this->api('deals'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_participant_can_update_deal_status(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $group = Group::factory()->create(['owner_id' => $seller->id]);
        $group->members()->syncWithoutDetaching([
            $seller->id => ['role_in_group' => 'owner'],
            $buyer->id => ['role_in_group' => 'member'],
        ]);

        $deal = Deal::factory()->create([
            'seller_agent_id' => $seller->id,
            'buyer_agent_id' => $buyer->id,
            'property_id' => Property::factory()->create([
                'group_id' => $group->id,
                'owner_id' => $seller->id,
            ])->id,
            'status' => 'initiated',
        ]);

        $this->actingAs($seller)
            ->patchJson($this->api("deals/{$deal->id}"), [
                'status' => 'proposal',
            ])
            ->assertOk()
            ->assertJsonFragment([
                'status' => 'proposal',
            ]);
    }

    public function test_non_participant_cannot_update_deal(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $outsider = User::factory()->create();

        $group = Group::factory()->create(['owner_id' => $seller->id]);
        $group->members()->syncWithoutDetaching([
            $seller->id => ['role_in_group' => 'owner'],
            $buyer->id => ['role_in_group' => 'member'],
        ]);

        $deal = Deal::factory()->create([
            'seller_agent_id' => $seller->id,
            'buyer_agent_id' => $buyer->id,
            'property_id' => Property::factory()->create([
                'group_id' => $group->id,
                'owner_id' => $seller->id,
            ])->id,
        ]);

        $this->actingAs($outsider)
            ->patchJson($this->api("deals/{$deal->id}"), [
                'status' => 'proposal',
            ])
            ->assertForbidden();
    }

    protected function api(string $path): string
    {
        $version = config('app.api_version');

        return sprintf('/api/%s/%s', $version, ltrim($path, '/'));
    }
}
