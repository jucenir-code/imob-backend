<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Minishlink\WebPush\VAPID;

class WebPushKeys
{
    public function credentials(): array
    {
        $encrypted = DB::table('web_push_keys')->where('id', 1)->value('keypair');
        if (! $encrypted) {
            // Shared database keeps the same identity across deploys and workers.
            DB::table('web_push_keys')->insertOrIgnore([
                'id' => 1,
                'keypair' => Crypt::encryptString(json_encode(VAPID::createVapidKeys(), JSON_THROW_ON_ERROR)),
            ]);
            $encrypted = DB::table('web_push_keys')->where('id', 1)->value('keypair');
        }

        return ['subject' => config('webpush.subject') ?: config('app.url')] + json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
    }
}
