<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function view(User $user, Property $property): bool
    {
        return true;
    }

    public function create(User $user, Group $group): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->groups()->where('groups.id', $group->id)->exists();
    }

    public function update(User $user, Property $property): bool
    {
        if ($property->owner_id === $user->id) {
            return true;
        }

        return $this->canManageWithinGroup($user, $property->group_id);
    }

    public function delete(User $user, Property $property): bool
    {
        return $this->update($user, $property);
    }

    protected function canManageWithinGroup(User $user, int $groupId): bool
    {
        return $user->groups()
            ->where('groups.id', $groupId)
            ->wherePivotIn('role_in_group', ['owner', 'moderator'])
            ->exists();
    }
}
