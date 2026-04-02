<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * @mixin Model
 */
trait BelongsToGroup
{
    public function scopeForGroup(Builder $query, int|string $groupId): Builder
    {
        return $query->where($this->qualifyColumn('group_id'), $groupId);
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $query->whereIn($this->qualifyColumn('group_id'), function (QueryBuilder $subquery) use ($user) {
            $subquery
                ->select('group_user.group_id')
                ->from('group_user')
                ->where('group_user.user_id', $user->getAuthIdentifier());
        });
    }
}
