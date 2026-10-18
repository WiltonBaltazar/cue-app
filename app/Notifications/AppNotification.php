<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base class for every Cue App notification (contracts/notifications.md).
 *
 * Always stored in-app (FR-040); push is added per the user's preference in User Story 6.
 * Queued, and dispatched only after the surrounding transaction commits (queue `after_commit`).
 * Title and body must never contain sensitive data (Principle V, FR-036).
 */
abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
        return ['database'];
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
