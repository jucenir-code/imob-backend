<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'owner_id' => $this->owner_id,
            'type' => $this->type,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'parking' => $this->parking,
            'area_m2' => $this->area_m2,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'price' => $this->price,
            'price_visibility' => $this->price_visibility,
            'status' => $this->status,
            'cover_image_url' => $this->cover_image_url,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'group' => new GroupResource($this->whenLoaded('group')),
            'owner' => new UserResource($this->whenLoaded('owner')),
            'images' => PropertyImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
