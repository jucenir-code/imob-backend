<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushNotificationService
{
    public function sendToTokens(iterable $tokens, array $payload): void
    {
        $validTokens = Collection::make($tokens)
            ->filter(fn ($token) => is_string($token) && preg_match('/^(ExponentPushToken|ExpoPushToken)\[.+\]$/', $token) === 1)
            ->values();

        if ($validTokens->isEmpty()) {
            return;
        }

        foreach ($validTokens->chunk(100) as $chunk) {
            $messages = $chunk->map(fn ($token) => array_merge($payload, ['to' => $token]))->all();

            try {
                Http::timeout(10)
                    ->acceptJson()
                    ->post('https://exp.host/--/api/v2/push/send', $messages)
                    ->throw();
            } catch (\Throwable $exception) {
                Log::warning('Falha ao enviar push notification via Expo.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
