<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'visibility' => $this->visibility,
            'owner_id' => $this->owner_id,
            'members_count' => $this->when(isset($this->members_count), $this->members_count),
            'properties_active_count' => $this->when(isset($this->properties_active_count), $this->properties_active_count),
            'role_in_group' => $this->whenPivotLoaded('group_user', function () {
                return $this->pivot->role_in_group;
            }),
            'members' => UserResource::collection($this->whenLoaded('members')),
        ];
    }
}
