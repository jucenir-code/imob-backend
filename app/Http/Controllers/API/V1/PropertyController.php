<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Property\PropertyStoreRequest;
use App\Http\Requests\Property\PropertyUpdateRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Property;
use App\Models\User;
use App\Services\NewPropertyPushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropertyController extends Controller
{
    public function __construct(
        private readonly NewPropertyPushNotifier $newPropertyPushNotifier
    ) {
    }

    public function index(Request $request)
    {
        $query = Property::query()
            ->with(['group.owner', 'owner']);

        if ($groupId = $request->integer('group_id')) {
            $query->forGroup($groupId);
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        if ($bedrooms = $request->integer('bedrooms')) {
            $query->where('bedrooms', $bedrooms);
        }

        if ($neighborhood = $request->string('neighborhood')->trim()->toString()) {
            $query->where('neighborhood', 'like', "%{$neighborhood}%");
        }

        if ($priceMin = $request->input('price_min')) {
            $query->where('price', '>=', $priceMin);
        }

        if ($priceMax = $request->input('price_max')) {
            $query->where('price', '<=', $priceMax);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        } else {
            $query->where('status', 'active');
        }

        $properties = $query->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        $properties->getCollection()->transform(function (Property $property) {
            return $this->ensurePropertyOwner($property);
        });

        return PropertyResource::collection($properties);
    }

    public function store(PropertyStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $groupId = $request->validated('group_id') ?: $user->groups()->value('groups.id');
        $groupId ??= $this->ensureUserDefaultGroup($user)->id;

        $group = Group::query()->findOrFail($groupId);

        $this->authorize('create', [Property::class, $group]);

        $data = $request->validated();
        $slugBase = Str::slug($data['title']);

        $property = DB::transaction(function () use ($data, $user, $group, $slugBase) {
            $slug = $this->generateUniqueSlug($slugBase, $group->id);

            /** @var Property $property */
            $property = Property::query()->create(array_merge($data, [
                'group_id' => $group->id,
                'owner_id' => $user->id,
                'slug' => $slug,
                'status' => $data['status'] ?? 'active',
                'price_visibility' => $data['price_visibility'] ?? 'show',
            ]));

            if ((int) $property->owner_id !== (int) $user->id) {
                $property->owner_id = $user->id;
                $property->save();
            }

            return $this->ensurePropertyOwner($property->load(['group.owner', 'owner']));
        });

        $this->newPropertyPushNotifier->notify($property, $user);

        return (new PropertyResource($property))->response()->setStatusCode(201);
    }

    public function show(Property $property): PropertyResource
    {
        $this->authorize('view', $property);

        return new PropertyResource($this->ensurePropertyOwner($property->load(['group.owner', 'owner', 'images'])));
    }

    public function update(PropertyUpdateRequest $request, Property $property): PropertyResource
    {
        $this->authorize('update', $property);

        $property->fill($request->validated());
        $property->save();

        return new PropertyResource($this->ensurePropertyOwner($property->refresh()->load(['group.owner', 'owner'])));
    }

    public function destroy(Property $property): JsonResponse
    {
        $this->authorize('delete', $property);

        $property->delete();

        return response()->json([], 204);
    }

    protected function generateUniqueSlug(string $base, int $groupId): string
    {
        $slug = $base;
        $counter = 1;

        while (Property::query()->where('group_id', $groupId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    protected function ensurePropertyOwner(Property $property): Property
    {
        if (! $property->relationLoaded('owner') || ! $property->owner) {
            $fallbackOwner = $property->group?->owner;

            if ($fallbackOwner) {
                if (! $property->owner_id) {
                    $property->owner_id = $fallbackOwner->id;
                }
                $property->setRelation('owner', $fallbackOwner);
            }
        }

        return $property;
    }

    protected function ensureUserDefaultGroup(User $user): Group
    {
        $group = Group::query()->create([
            'name' => sprintf('Carteira de %s', $user->name),
            'visibility' => 'private',
            'owner_id' => $user->id,
        ]);

        GroupUser::query()->updateOrCreate(
            [
                'group_id' => $group->id,
                'user_id' => $user->id,
            ],
            [
                'role_in_group' => 'owner',
            ]
        );

        return $group;
    }
}
