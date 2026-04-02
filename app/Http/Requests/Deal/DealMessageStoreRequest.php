<?php

namespace App\Http\Requests\Deal;

use Illuminate\Foundation\Http\FormRequest;

class DealMessageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array', 'max:6'],
            'attachments.*' => [
                'nullable',
                'file',
                'max:20480',
                'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif,audio/mpeg,audio/mp4,audio/x-m4a,audio/aac,audio/wav,audio/webm,audio/ogg',
            ],
            'attachment_duration_ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $message = (string) $this->input('message', '');

            if (! $this->filled('message') && ! $this->hasFile('attachments')) {
                $validator->errors()->add('message', 'Envie uma mensagem, imagem ou áudio.');
            }

            if ($message !== '') {
                if ($this->containsEmail($message)) {
                    $validator->errors()->add('message', 'Não é permitido compartilhar e-mail no chat. Use apenas a conversa interna.');
                }

                if ($this->containsPhoneNumber($message)) {
                    $validator->errors()->add('message', 'Não é permitido compartilhar telefone no chat. Use apenas a conversa interna.');
                }

                if ($this->containsContactLink($message)) {
                    $validator->errors()->add('message', 'Não é permitido compartilhar links de contato no chat.');
                }
            }

            if (! $this->hasFile('attachments')) {
                return;
            }

            $imageCount = 0;
            $audioCount = 0;

            foreach ($this->file('attachments', []) as $attachment) {
                $mimeType = (string) $attachment->getMimeType();

                if (str_starts_with($mimeType, 'image/')) {
                    $imageCount++;
                } elseif (str_starts_with($mimeType, 'audio/')) {
                    $audioCount++;
                }
            }

            if ($imageCount > 0 && $audioCount > 0) {
                $validator->errors()->add('attachments', 'Envie imagens e áudio em mensagens separadas.');
            }

            if ($audioCount > 1) {
                $validator->errors()->add('attachments', 'Envie apenas um áudio por mensagem.');
            }
        });
    }

    private function containsEmail(string $message): bool
    {
        return preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $message) === 1;
    }

    private function containsPhoneNumber(string $message): bool
    {
        if (preg_match('/(?:\+?\d[\d\s().-]{7,}\d)/', $message) === 1) {
            return true;
        }

        $digitsOnly = preg_replace('/\D+/', '', $message);

        return is_string($digitsOnly) && strlen($digitsOnly) >= 8;
    }

    private function containsContactLink(string $message): bool
    {
        return preg_match('/(?:wa\.me|whatsapp\.com|t\.me|telegram\.me|mailto:)/i', $message) === 1;
    }
}
