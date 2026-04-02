<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Group\GroupStoreRequest;
use App\Http\Requests\Group\GroupUpdateRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = $user->groups()
            ->withPivot('role_in_group')
            ->withCount([
                'properties as properties_active_count' => fn ($q) => $q->where('status', 'active'),
                'members',
            ])
            ->orderBy('name');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($role = $request->string('role')->trim()->toString()) {
            $query->wherePivot('role_in_group', $role);
        }

        $groups = $query->paginate($request->integer('per_page', 15));

        return GroupResource::collection($groups);
    }

    public function store(GroupStoreRequest $request): JsonResponse
    {
        $user = $request->user();

        $group = DB::transaction(function () use ($request, $user) {
            /** @var Group $group */
            $group = Group::query()->create([
                'name' => $request->validated('name'),
                'visibility' => 'private',
                'owner_id' => $user->id,
            ]);

            $group->members()->attach($user->id, [
                'role_in_group' => 'owner',
            ]);

            return $group->fresh([
                'members' => fn ($query) => $query->where('users.id', $user->id),
            ]);
        });

        $resource = new GroupResource(
            $group->loadCount([
                'properties as properties_active_count' => fn ($q) => $q->where('status', 'active'),
                'members',
            ])
        );

        return $resource->response()->setStatusCode(201);
    }

    public function show(Group $group, Request $request): GroupResource
    {
        $this->authorize('view', $group);

        $group->loadCount([
            'properties as properties_active_count' => fn ($q) => $q->where('status', 'active'),
            'members',
        ]);

        if ($request->boolean('with_members')) {
            $group->load([
                'members' => fn ($query) => $query->withPivot('role_in_group')->orderBy('name'),
            ]);
        }

        return new GroupResource($group);
    }

    public function update(GroupUpdateRequest $request, Group $group): GroupResource
    {
        $this->authorize('update', $group);

        $group->fill($request->validated());
        $group->save();

        return new GroupResource($group->fresh()->loadCount([
            'properties as properties_active_count' => fn ($q) => $q->where('status', 'active'),
            'members',
        ]));
    }

    public function destroy(Group $group): JsonResponse
    {
        $this->authorize('delete', $group);

        DB::transaction(function () use ($group) {
            $group->members()->detach();
            $group->delete();
        });

        return response()->json([], 204);
    }
}
