<?php

namespace App\Notifications\Push;

use App\Notifications\AppNotification;
use RuntimeException;

class PushDeliveryFailed extends RuntimeException
{
    /**
     * Only the type and HTTP statuses: endpoints identify devices and stay out of logs.
     *
     * @param  array<int, int>  $statuses
     */
    public static function for(AppNotification $notification, array $statuses): self
    {
        return new self(sprintf(
            'Push "%s" failed on %d device(s), statuses: %s.',
            $notification::type(),
            count($statuses),
            implode(', ', $statuses),
        ));
    }
}
