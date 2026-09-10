<?php

namespace Tests\Feature;

use App\Jobs\NotifyWebPush;
use App\Jobs\SendWebPush;
use App\Models\Deal;
use App\Models\Property;
use App\Models\User;
use App\Models\WebPushSubscription;
use App\Services\WebPushKeys;
use App\Services\WebPushTransport;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\VAPID;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        RefreshDatabaseState::$migrated = false;
    }

    private function payload(string $suffix = 'test'): array
    {
        return ['endpoint' => 'https://fcm.googleapis.com/fcm/send/'.$suffix, 'keys' => ['p256dh' => VAPID::createVapidKeys()['publicKey'], 'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')]];
    }

    private function subscribe(User $user): WebPushSubscription
    {
        $payload = $this->payload((string) $user->id);

        return WebPushSubscription::create(['user_id' => $user->id, 'endpoint_hash' => hash('sha256', $payload['endpoint']), 'endpoint' => $payload['endpoint'], 'public_key' => $payload['keys']['p256dh'], 'auth_token' => $payload['keys']['auth']]);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['status' => 'active', 'is_approved' => true, 'role' => 'agent']);
    }

    public function test_credentials_are_persistent_encrypted_and_private(): void
    {
        $this->getJson('/web/push/config')->assertUnauthorized();
        $this->actingAs($this->user(), 'web');
        $first = $this->getJson('/web/push/config')->assertOk()->assertJsonMissingPath('privateKey')->json('public_key');
        $this->assertSame($first, $this->getJson('/web/push/config')->json('public_key'));
        $credentials = app(WebPushKeys::class)->credentials();
        $this->assertStringNotContainsString($credentials['privateKey'], DB::table('web_push_keys')->value('keypair'));
    }

    public function test_subscription_ownership_and_endpoint_validation(): void
    {
        $payload = $this->payload();
        $this->postJson('/web/push/subscriptions', $payload)->assertUnauthorized();
        $a = $this->user();
        $b = $this->user();
        $this->actingAs($a, 'web')->postJson('/web/push/subscriptions', $payload)->assertOk();
        $this->postJson('/web/push/subscriptions', $payload)->assertOk();
        $this->assertDatabaseCount('web_push_subscriptions', 1);
        $this->actingAs($b, 'web')->deleteJson('/web/push/subscriptions', ['endpoint' => $payload['endpoint']])->assertNoContent();
        $this->assertDatabaseHas('web_push_subscriptions', ['user_id' => $a->id]);
        // The current browser may explicitly bind its subscription to its new account.
        $this->postJson('/web/push/subscriptions', $payload)->assertOk();
        $this->assertDatabaseHas('web_push_subscriptions', ['user_id' => $b->id]);
        foreach (['https://127.0.0.1/push', 'http://fcm.googleapis.com/push', 'https://fcm.googleapis.com.evil.test/push', 'https://fcm.googleapis.com:8080/push', 'https://user@fcm.googleapis.com/push'] as $endpoint) {
            $this->postJson('/web/push/subscriptions', array_replace($payload, ['endpoint' => $endpoint]))->assertUnprocessable();
        }
        $this->deleteJson('/web/push/subscriptions', ['endpoint' => $payload['endpoint']])->assertNoContent();
        $this->assertDatabaseCount('web_push_subscriptions', 0);
    }

    public function test_server_logout_removes_only_this_browser_subscription(): void
    {
        $user = $this->user();
        $this->actingAs($user, 'web')->postJson('/web/push/subscriptions', $this->payload('phone'))->assertOk();
        $other = $this->subscribe($user);
        $this->postJson('/logout')->assertNoContent();
        $this->assertDatabaseCount('web_push_subscriptions', 1);
        $this->assertModelExists($other);
    }

    public function test_push_mutations_require_csrf(): void
    {
        $this->app->bind(\App\Http\Middleware\VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends \App\Http\Middleware\VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->actingAs($this->user(), 'web')->postJson('/web/push/subscriptions', $this->payload())->assertStatus(419);
    }

    public function test_property_alerts_only_target_other_active_approved_users(): void
    {
        Queue::fake();
        $actor = $this->user();
        $recipient = $this->user();
        foreach ([$actor, $recipient, $this->user(['status' => 'suspended']), $this->user(['is_approved' => false])] as $user) {
            $this->subscribe($user);
        }
        $property = Property::factory()->create(['owner_id' => $actor->id]);
        Queue::assertPushed(NotifyWebPush::class, fn ($job) => $job->type === 'property' && $job->eventId === $property->id);
        (new NotifyWebPush('property', $property->id))->handle();
        Queue::assertPushed(SendWebPush::class, 1);
        Queue::assertPushed(SendWebPush::class, fn ($job) => $job->recipientId === $recipient->id);
        Queue::fake();
        Property::factory()->create(['owner_id' => $actor->id, 'status' => 'draft']);
        Queue::assertNotPushed(NotifyWebPush::class);
    }

    public function test_messages_notify_only_the_other_participant(): void
    {
        Queue::fake();
        $actor = $this->user();
        $recipient = $this->user();
        $stranger = $this->user();
        foreach ([$actor, $recipient, $stranger] as $user) {
            $this->subscribe($user);
        }
        $property = Property::factory()->create(['owner_id' => $recipient->id]);
        $deal = Deal::create(['property_id' => $property->id, 'seller_agent_id' => $recipient->id, 'buyer_agent_id' => $actor->id, 'status' => 'proposal', 'buyer_name' => 'Client']);
        $message = $deal->messages()->create(['user_id' => $actor->id, 'message' => 'Olá']);
        Queue::assertPushed(NotifyWebPush::class, fn ($job) => $job->type === 'message' && $job->eventId === $message->id);
        (new NotifyWebPush('message', $message->id))->handle();
        Queue::assertPushed(SendWebPush::class, 1);
        Queue::assertPushed(SendWebPush::class, fn ($job) => $job->recipientId === $recipient->id);
        $subscription = WebPushSubscription::where('user_id', $recipient->id)->firstOrFail();
        $transport = $this->mock(WebPushTransport::class);
        $transport->shouldReceive('send')->once()->withArgs(fn ($sub, $payload) => $payload['url'] === '/app/negociacoes/'.$deal->id && ! str_contains($payload['body'], 'Olá'))->andReturnTrue();
        (new SendWebPush($subscription->id, $recipient->id, 'message', $message->id))->handle($transport);
    }

    public function test_expired_subscriptions_are_deleted_and_reassigned_accounts_are_not_notified(): void
    {
        Queue::fake();
        $actor = $this->user();
        $recipient = $this->user();
        $property = Property::factory()->create(['owner_id' => $actor->id]);
        $subscription = $this->subscribe($recipient);
        $transport = $this->mock(WebPushTransport::class);
        $transport->shouldReceive('send')->once()->andReturnFalse();
        $job = new SendWebPush($subscription->id, $recipient->id, 'property', $property->id);
        $subscription->update(['user_id' => $actor->id]);
        $job->handle($transport);
        $subscription->update(['user_id' => $recipient->id]);
        $job->handle($transport);
        $this->assertModelMissing($subscription);
    }

    public function test_transport_encrypts_payloads_and_handles_provider_expiration(): void
    {
        $subscription = $this->subscribe($this->user());
        $handler = new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(201), new \GuzzleHttp\Psr7\Response(410), new \GuzzleHttp\Psr7\Response(503),
        ]);
        config(['app.url' => 'https://cci.test']);
        $auth = app(WebPushKeys::class)->credentials();
        $transport = new class($handler, $auth) extends WebPushTransport
        {
            public function __construct(private $handler, private $auth) {}

            protected function client(): \Minishlink\WebPush\WebPush
            {
                return new \Minishlink\WebPush\WebPush(['VAPID' => $this->auth], [], 15, ['handler' => \GuzzleHttp\HandlerStack::create($this->handler), 'allow_redirects' => false]);
            }
        };
        $payload = ['title' => 'Teste', 'body' => 'Conteudo que precisa ser cifrado'];
        $this->assertTrue($transport->send($subscription, $payload));
        $this->assertSame('aes128gcm', $handler->getLastRequest()->getHeaderLine('Content-Encoding'));
        $this->assertStringContainsString('vapid', $handler->getLastRequest()->getHeaderLine('Authorization'));
        $this->assertStringNotContainsString($payload['body'], (string) $handler->getLastRequest()->getBody());
        $this->assertFalse($transport->send($subscription, $payload));
        $this->expectException(\RuntimeException::class);
        $transport->send($subscription, $payload);
    }

    public function test_notifications_are_enqueued_only_after_commit(): void
    {
        config(['queue.default' => 'database']);
        $actor = $this->user();
        DB::beginTransaction();
        Property::factory()->create(['owner_id' => $actor->id]);
        $this->assertDatabaseCount('jobs', 0);
        DB::rollBack();
        $this->assertDatabaseCount('jobs', 0);
        DB::transaction(fn () => Property::factory()->create(['owner_id' => $actor->id]));
        $this->assertDatabaseCount('jobs', 1);
    }
}
