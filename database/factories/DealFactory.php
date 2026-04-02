<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    public function definition(): array
    {
        $property = Property::factory()->create();
        $seller = $property->owner;
        $buyer = User::factory()->create();

        $property->group->members()->syncWithoutDetaching([
            $seller->id => ['role_in_group' => 'owner'],
            $buyer->id => ['role_in_group' => 'member'],
        ]);

        return [
            'property_id' => $property->id,
            'seller_agent_id' => $seller->id,
            'buyer_agent_id' => $buyer->id,
            'buyer_name' => $buyer->name,
            'buyer_contact' => $buyer->phone_e164,
            'commission_percent' => 6.00,
            'commission_split_json' => ['seller_agent' => 50, 'buyer_agent' => 50],
            'status' => 'initiated',
            'notes' => null,
            'started_at' => now(),
        ];
    }
}
