<?php

namespace App\Notifications\Push;

use NotificationChannels\WebPush\WebPushMessageInterface;

/**
 * The exact payload the service worker reads (contracts/notifications.md). Kept flat and
 * minimal on purpose: title, short body, in-app path, tag and type, plus optional actions.
 * Nothing else may be added here (Principle V).
 */
final readonly class PushMessage implements WebPushMessageInterface
{
    public const BODY_MAX_LENGTH = 120;

    /**
     * @param  list<array{action: string, title: string, url: string}>  $actions
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public string $tag,
        public string $type,
        public int $ttl,
        public string $urgency,
        public array $actions = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tag' => $this->tag,
            'type' => $this->type,
            'actions' => $this->actions,
        ], fn (mixed $value): bool => $value !== []);
    }

    /**
     * @return array{TTL: int, urgency: string}
     */
    public function getOptions(): array
    {
        return ['TTL' => $this->ttl, 'urgency' => $this->urgency];
    }
}
