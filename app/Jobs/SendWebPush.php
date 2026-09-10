<?php

namespace App\Jobs;

use App\Models\DealMessage;
use App\Models\Property;
use App\Models\WebPushSubscription;
use App\Services\WebPushTransport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Gate;

class SendWebPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(public int $subscriptionId, public int $recipientId, public string $type, public int $eventId) {}

    public function handle(WebPushTransport $transport): void
    {
        if (! config('webpush.enabled')) {
            return;
        }
        $subscription = WebPushSubscription::with('user')->find($this->subscriptionId);
        $user = $subscription?->user;
        if (! $user || $user->id !== $this->recipientId || $user->status !== 'active') {
            return;
        }
        if ($this->type === 'message') {
            $message = DealMessage::with('deal')->find($this->eventId);
            $deal = $message?->deal;
            if (! $deal || $message->user_id === $user->id || ! in_array($user->id, [$deal->seller_agent_id, $deal->buyer_agent_id]) || ! Gate::forUser($user)->allows('view', $deal)) {
                return;
            }
            $payload = ['title' => 'Nova mensagem', 'body' => 'Você recebeu uma mensagem em uma negociação.', 'url' => '/app/negociacoes/'.$deal->id, 'tag' => 'deal-'.$deal->id];
        } else {
            $property = Property::find($this->eventId);
            if (! $property || $property->status !== 'active' || $property->owner_id === $user->id || (! $user->is_approved && $user->role !== 'admin') || ! Gate::forUser($user)->allows('view', $property)) {
                return;
            }
            $payload = ['title' => 'Novo imóvel cadastrado', 'body' => 'Um novo imóvel está disponível na rede.', 'url' => '/app/imoveis/'.$property->id, 'tag' => 'property-'.$property->id];
        }
        $payload['user_id'] = $user->id;
        if (! $transport->send($subscription, $payload)) {
            $subscription->delete();
        }
    }
}
