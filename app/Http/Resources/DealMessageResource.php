<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $firstAttachment = $this->relationLoaded('attachments') ? $this->attachments->first() : null;

        return [
            'id' => $this->id,
            'deal_id' => $this->deal_id,
            'user_id' => $this->user_id,
            'message' => $this->message,
            'attachment_type' => $firstAttachment?->type,
            'attachment_url' => $firstAttachment?->url,
            'attachment_mime_type' => $firstAttachment?->mime_type,
            'attachment_original_name' => $firstAttachment?->original_name,
            'attachment_duration_ms' => $firstAttachment?->duration_ms,
            'attachments' => DealMessageAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
            'author' => new UserResource($this->whenLoaded('author')),
        ];
    }
}
