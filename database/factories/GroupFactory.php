<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'name' => sprintf('%s Circle', fake()->city()),
            'visibility' => 'private',
            'owner_id' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Group $group) {
            $group->members()->attach($group->owner_id, ['role_in_group' => 'owner']);
        });
    }
}
