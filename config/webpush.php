<?php

use NotificationChannels\WebPush\PushSubscription;

/*
|--------------------------------------------------------------------------
| Web Push (research R12)
|--------------------------------------------------------------------------
|
| VAPID keys identify this server to the browsers' push services. Generate
| them once with `php artisan webpush:vapid`; changing them invalidates every
| stored subscription. The public key is shared with the front end to subscribe.
|
*/

return [

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    'model' => PushSubscription::class,

    'table_name' => 'push_subscriptions',

    // null: the application's default connection.
    'database_connection' => null,

    'client_options' => [
        'timeout' => 10,
    ],

    'automatic_padding' => env('WEBPUSH_AUTOMATIC_PADDING', true),

];
