<?php

namespace App\Services;

use App\Models\WebPushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

class WebPushTransport
{
    public function __construct(private WebPushKeys $keys) {}

    protected function client(): WebPush
    {
        return new WebPush(['VAPID' => $this->keys->credentials()], ['TTL' => 86400], 15, ['allow_redirects' => false]);
    }

    public function send(WebPushSubscription $subscription, array $payload): bool
    {
        $push = $this->client();
        $report = $push->sendOneNotification(Subscription::create([
            'endpoint' => $subscription->endpoint,
            'contentEncoding' => 'aes128gcm',
            'keys' => ['p256dh' => $subscription->public_key, 'auth' => $subscription->auth_token],
        ]), json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        if ($report->isSubscriptionExpired()) {
            return false;
        }
        if (! $report->isSuccess()) {
            throw new RuntimeException('Web Push delivery failed; status '.($report->getResponse()?->getStatusCode() ?? 'network'));
        }

        return true;
    }
}
