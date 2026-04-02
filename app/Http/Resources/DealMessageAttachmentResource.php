<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealMessageAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'url' => $this->url,
            'mime_type' => $this->mime_type,
            'original_name' => $this->original_name,
            'duration_ms' => $this->duration_ms,
            'position' => $this->position,
        ];
    }
}
