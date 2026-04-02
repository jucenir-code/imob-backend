<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;

class NewPropertyPushNotifier
{
    public function __construct(
        private readonly ExpoPushNotificationService $pushService
    ) {
    }

    public function notify(Property $property, User $actor): void
    {
        $property->loadMissing(['group', 'owner']);

        $recipientTokens = User::query()
            ->where('is_approved', true)
            ->where('id', '!=', $actor->id)
            ->whereHas('groups', fn ($query) => $query->where('groups.id', $property->group_id))
            ->with('pushTokens')
            ->get()
            ->pluck('pushTokens')
            ->flatten()
            ->pluck('token')
            ->unique()
            ->values();

        $this->pushService->sendToTokens($recipientTokens, [
            'title' => 'Novo imóvel cadastrado',
            'body' => sprintf('%s em %s - %s', $property->title, $property->city, $property->state),
            'sound' => 'default',
            'data' => [
                'type' => 'property.created',
                'property_id' => $property->id,
                'group_id' => $property->group_id,
            ],
        ]);
    }
}
