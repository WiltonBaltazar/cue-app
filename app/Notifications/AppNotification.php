<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Push\PushChannel;
use App\Notifications\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Base class for every Cue App notification (contracts/notifications.md).
 *
 * Always stored in-app (FR-040); also pushed to the user's devices unless they switched
 * this type off (FR-025). Queued, and dispatched only after the surrounding transaction
 * commits (queue `after_commit`). Title and body must never contain sensitive data
 * (Principle V, FR-036).
 */
abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Failed pushes are retried; the in-app copy is a separate job (contracts/notifications.md).
     */
    public int $tries = 3;

    /**
     * Notification type identifier, e.g. "schedule_published".
     */
    abstract public static function type(): string;

    abstract public function title(): string;

    abstract public function body(): string;

    /**
     * Relative in-app path the notification opens.
     */
    abstract public function url(): string;

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->wantsPush(static::type()) && $notifiable->pushSubscriptions()->exists()
            ? ['database', PushChannel::class]
            : ['database'];
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    /**
     * Same item, same tag: a newer push replaces the older one on the device.
     */
    public function pushTag(): string
    {
        return static::type();
    }

    /**
     * Seconds the push service keeps trying to deliver.
     */
    public function pushTtl(): int
    {
        return 24 * 60 * 60;
    }

    /**
     * "high" only for what cannot wait: urgent swaps and cancellations.
     */
    public function pushUrgency(): string
    {
        return 'normal';
    }

    /**
     * Buttons on the notification, each opening an in-app path.
     *
     * @return list<array{action: string, title: string, url: string}>
     */
    public function pushActions(): array
    {
        return [];
    }

    public function toWebPush(User $notifiable): PushMessage
    {
        return new PushMessage(
            title: $this->title(),
            body: Str::limit($this->body(), PushMessage::BODY_MAX_LENGTH - 1, '…'),
            url: $this->url(),
            tag: $this->pushTag(),
            type: static::type(),
            ttl: max(60, $this->pushTtl()),
            urgency: $this->pushUrgency(),
            actions: $this->pushActions(),
        );
    }

    /**
     * @return array{type: string, title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => static::type(),
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
        ];
    }
}
