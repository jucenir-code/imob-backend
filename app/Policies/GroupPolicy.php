<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function view(User $user, Group $group): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $group->members()->whereKey($user->id)->exists();
    }

    public function update(User $user, Group $group): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $this->isOwnerOrModerator($user, $group);
    }

    public function delete(User $user, Group $group): bool
    {
        return $user->id === $group->owner_id;
    }

    public function manageMembers(User $user, Group $group): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $this->isOwnerOrModerator($user, $group);
    }

    protected function isOwnerOrModerator(User $user, Group $group): bool
    {
        if ($user->id === $group->owner_id) {
            return true;
        }

        return $group->members()
            ->wherePivot('role_in_group', 'moderator')
            ->where('users.id', $user->id)
            ->exists();
    }
}
