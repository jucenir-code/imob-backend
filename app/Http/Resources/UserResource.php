<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'phone_e164' => $this->phone_e164,
            'role' => $this->role,
            'status' => $this->status,
            'is_approved' => (bool) $this->is_approved,
            'approved_at' => $this->approved_at,
            'role_in_group' => $this->whenPivotLoaded('group_user', function () {
                return $this->pivot->role_in_group;
            }),
            'groups' => GroupResource::collection($this->whenLoaded('groups')),
        ];
    }
}
