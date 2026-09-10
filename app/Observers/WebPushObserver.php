<?php

namespace App\Observers;

use App\Jobs\NotifyWebPush;
use App\Models\DealMessage;
use App\Models\Property;

class WebPushObserver
{
    public function created(Property|DealMessage $model): void
    {
        if (! config('webpush.enabled')) {
            return;
        }
        if ($model instanceof Property && $model->status !== 'active') {
            return;
        }
        NotifyWebPush::dispatch($model instanceof Property ? 'property' : 'message', $model->id);
    }
}
