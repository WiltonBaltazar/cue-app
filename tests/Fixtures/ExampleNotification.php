<?php

namespace Tests\Fixtures;

use App\Notifications\AppNotification;

/**
 * Minimal concrete notification for testing the AppNotification plumbing.
 */
class ExampleNotification extends AppNotification
{
    public function __construct(private string $titleText = 'Nova escala publicada') {}

    public static function type(): string
    {
        return 'schedule_published';
    }

    public function title(): string
    {
        return $this->titleText;
    }

    public function body(): string
    {
        return 'Dom 2 Nov · Culto de Domingo · Áudio · chegada 07:30';
    }

    public function url(): string
    {
        return '/minha-escala';
    }
}
