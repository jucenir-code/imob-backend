<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        $title = sprintf('%s %s', fake()->randomElement(['Apartamento', 'Casa', 'Terreno']), fake()->streetName());

        return [
            'group_id' => Group::factory(),
            'owner_id' => null,
            'type' => fake()->randomElement(['apartment', 'house', 'land', 'commercial']),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraph(),
            'bedrooms' => fake()->numberBetween(1, 4),
            'bathrooms' => fake()->numberBetween(1, 3),
            'parking' => fake()->numberBetween(0, 2),
            'area_m2' => fake()->randomFloat(2, 45, 240),
            'neighborhood' => fake()->streetName(),
            'city' => fake()->city(),
            'state' => 'SC',
            'lat' => fake()->latitude(-28.7, -28.4),
            'lng' => fake()->longitude(-49.6, -49.2),
            'price' => fake()->randomFloat(2, 150000, 1500000),
            'price_visibility' => 'show',
            'status' => 'active',
            'whatsapp_owner' => fake()->numerify('+55############'),
            'cover_image_url' => fake()->imageUrl(),
            'published_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Property $property) {
            $group = $property->group ?? Group::find($property->group_id);

            if (! $property->owner_id && $group) {
                $property->owner_id = $group->owner_id;
            }
        })->afterCreating(function (Property $property) {
            $group = $property->group ?? Group::find($property->group_id);

            if (! $property->owner_id && $group) {
                $property->owner_id = $group->owner_id;
                $property->save();
            }
        });
    }
}
