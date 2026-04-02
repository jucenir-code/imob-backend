<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\Property;
use App\Models\User;

class DealPolicy
{
    public function view(User $user, Deal $deal): bool
    {
        return $this->isParticipant($user, $deal) || $this->isGroupAdmin($user, $deal->property->group_id);
    }

    public function create(User $user, Property $property): bool
    {
        return true;
    }

    public function update(User $user, Deal $deal): bool
    {
        return $this->isParticipant($user, $deal) || $this->isGroupAdmin($user, $deal->property->group_id);
    }

    public function message(User $user, Deal $deal): bool
    {
        if ($deal->status === 'initiated') {
            return false;
        }

        return $this->update($user, $deal);
    }

    protected function isParticipant(User $user, Deal $deal): bool
    {
        return $deal->seller_agent_id === $user->id || $deal->buyer_agent_id === $user->id;
    }

    protected function isGroupAdmin(User $user, int $groupId): bool
    {
        return $user->groups()
            ->where('groups.id', $groupId)
            ->wherePivotIn('role_in_group', ['owner', 'moderator'])
            ->exists();
    }
}
