<?php

namespace App\Jobs;

use App\Models\DealMessage;
use App\Models\Property;
use App\Models\WebPushSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyWebPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $type, public int $eventId)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        if (! config('webpush.enabled')) {
            return;
        }
        $query = WebPushSubscription::query()->whereHas('user', fn ($q) => $q->where('status', 'active'));
        if ($this->type === 'message') {
            $message = DealMessage::with('deal')->find($this->eventId);
            if (! $message?->deal) {
                return;
            }
            $query->whereIn('user_id', [$message->deal->seller_agent_id, $message->deal->buyer_agent_id])->where('user_id', '!=', $message->user_id);
        } else {
            $property = Property::find($this->eventId);
            if (! $property || $property->status !== 'active') {
                return;
            }
            $query->where('user_id', '!=', $property->owner_id)->whereHas('user', fn ($q) => $q->where('is_approved', true)->orWhere('role', 'admin'));
        }
        $query->chunkById(100, function ($subscriptions) {
            foreach ($subscriptions as $subscription) {
                SendWebPush::dispatch($subscription->id, $subscription->user_id, $this->type, $this->eventId);
            }
        });
    }
}
