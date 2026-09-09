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
            // Display the current terminology for previously stored automatic messages.
            'message' => match ($this->message) {
                'Conversa iniciada automaticamente. O proprietário já pode responder.' => 'Conversa iniciada automaticamente. O corretor já pode responder.',
                'Contato enviado. Aguarde o proprietário aceitar para liberar a conversa.' => 'Contato enviado. Aguarde o corretor aceitar para liberar a conversa.',
                default => $this->message,
            },
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
