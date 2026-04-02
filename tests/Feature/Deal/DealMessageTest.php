<?php

namespace Tests\Feature\Deal;

use App\Models\Deal;
use App\Models\Group;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_participants_can_list_messages(): void
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

        $deal = Deal::factory()->create([
            'property_id' => $property->id,
            'seller_agent_id' => $seller->id,
            'buyer_agent_id' => $buyer->id,
        ]);

        $deal->messages()->create([
            'user_id' => $seller->id,
            'message' => 'Olá, podemos negociar?'
        ]);

        $this->actingAs($buyer)
            ->getJson($this->api("deals/{$deal->id}/messages"))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_participant_can_send_message(): void
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

        $deal = Deal::factory()->create([
            'property_id' => $property->id,
            'seller_agent_id' => $seller->id,
            'buyer_agent_id' => $buyer->id,
        ]);

        $payload = ['message' => 'Tenho um comprador interessado.'];

        $this->actingAs($buyer)
            ->postJson($this->api("deals/{$deal->id}/messages"), $payload)
            ->assertCreated()
            ->assertJsonFragment($payload);

        $this->assertDatabaseHas('deal_messages', [
            'deal_id' => $deal->id,
            'user_id' => $buyer->id,
            'message' => 'Tenho um comprador interessado.',
        ]);
    }

    public function test_non_participant_cannot_send_message(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $outsider = User::factory()->create();

        $group = Group::factory()->create(['owner_id' => $seller->id]);
        $group->members()->syncWithoutDetaching([
            $seller->id => ['role_in_group' => 'owner'],
            $buyer->id => ['role_in_group' => 'member'],
        ]);

        $property = Property::factory()->create([
            'group_id' => $group->id,
            'owner_id' => $seller->id,
        ]);

        $deal = Deal::factory()->create([
            'property_id' => $property->id,
            'seller_agent_id' => $seller->id,
            'buyer_agent_id' => $buyer->id,
        ]);

        $this->actingAs($outsider)
            ->postJson($this->api("deals/{$deal->id}/messages"), ['message' => 'Posso ajudar?'])
            ->assertForbidden();
    }

    protected function api(string $path): string
    {
        $version = config('app.api_version');

        return sprintf('/api/%s/%s', $version, ltrim($path, '/'));
    }
}
