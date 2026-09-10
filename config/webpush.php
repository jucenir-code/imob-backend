<?php

return [
    'enabled' => (bool) env('WEB_PUSH_ENABLED', true),
    'subject' => env('WEB_PUSH_SUBJECT'),
    'allowed_hosts' => ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com', 'wns.windows.com'],
];
