<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'seller_agent_id' => $this->seller_agent_id,
            'buyer_agent_id' => $this->buyer_agent_id,
            'buyer_name' => $this->buyer_name,
            'buyer_contact' => $this->buyer_contact,
            'commission_percent' => $this->commission_percent,
            'commission_split' => $this->commission_split_json,
            'status' => $this->status,
            'notes' => $this->notes,
            'started_at' => $this->started_at,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'property' => new PropertyResource($this->whenLoaded('property')),
            'seller_agent' => new UserResource($this->whenLoaded('sellerAgent')),
            'buyer_agent' => new UserResource($this->whenLoaded('buyerAgent')),
            'messages' => DealMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
