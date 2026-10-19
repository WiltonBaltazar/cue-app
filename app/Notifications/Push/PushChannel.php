<?php

namespace App\Notifications\Push;

use App\Models\User;
use App\Notifications\AppNotification;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Web Push delivery (R12). The package channel sends to every device of the user and
 * deletes subscriptions the push service reports as gone (404/410). Any other failure is
 * thrown so the queued notification is retried with backoff; the in-app copy is a separate
 * job and is never affected.
 */
class PushChannel
{
    public function __construct(private WebPushChannel $channel) {}

    public function send(User $notifiable, AppNotification $notification): void
    {
        $failed = collect($this->channel->send($notifiable, $notification))
            ->reject(fn (MessageSentReport $report): bool => $report->isSuccess() || $report->isSubscriptionExpired());

        if ($failed->isNotEmpty()) {
            throw PushDeliveryFailed::for($notification, $failed->map(
                fn (MessageSentReport $report): int => $report->getResponse()?->getStatusCode() ?? 0,
            )->values()->all());
        }
    }
}
