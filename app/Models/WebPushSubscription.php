<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebPushSubscription extends Model
{
    protected $guarded = [];

    protected $hidden = ['endpoint', 'public_key', 'auth_token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
