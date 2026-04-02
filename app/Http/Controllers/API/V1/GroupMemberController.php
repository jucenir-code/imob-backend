<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Group\GroupMemberStoreRequest;
use App\Http\Requests\Group\GroupMemberUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupMemberController extends Controller
{
    public function store(GroupMemberStoreRequest $request, Group $group): JsonResponse
    {
        $this->authorize('manageMembers', $group);

        $userId = $request->validated('user_id');

        if ($group->members()->whereKey($userId)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => __('User already belongs to this group.'),
            ]);
        }

        $group->members()->attach($userId, [
            'role_in_group' => $request->validated('role_in_group', 'member'),
        ]);

        $member = $group->members()->where('users.id', $userId)->firstOrFail();

        return (new UserResource($member))->response()->setStatusCode(201);
    }

    public function update(GroupMemberUpdateRequest $request, Group $group, User $member): UserResource
    {
        $this->authorize('manageMembers', $group);

        if ($member->id === $group->owner_id) {
            throw ValidationException::withMessages([
                'role_in_group' => __('Owner role cannot be modified.'),
            ]);
        }

        if (! $group->members()->whereKey($member->id)->exists()) {
            abort(404);
        }

        $group->members()->updateExistingPivot($member->id, [
            'role_in_group' => $request->validated('role_in_group'),
        ]);

        $freshMember = $group->members()->where('users.id', $member->id)->firstOrFail();

        return new UserResource($freshMember);
    }

    public function destroy(Group $group, User $member): JsonResponse
    {
        $this->authorize('manageMembers', $group);

        if ($member->id === $group->owner_id) {
            throw ValidationException::withMessages([
                'user_id' => __('Owner cannot be removed from the group.'),
            ]);
        }

        if (! $group->members()->whereKey($member->id)->exists()) {
            abort(404);
        }

        DB::transaction(function () use ($group, $member) {
            $group->members()->detach($member->id);
        });

        return response()->json([], 204);
    }
}
