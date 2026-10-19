<?php

use App\Logging\ScrubSensitiveData;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;

beforeEach(function () {
    config(['logging.channels.scrub-test' => [
        'driver' => 'monolog',
        'handler' => TestHandler::class,
        'tap' => [ScrubSensitiveData::class],
    ]]);

    $this->handler = Log::channel('scrub-test')->getLogger()->getHandlers()[0];
});

test('passwords, tokens and personal answers never reach the logs', function () {
    Log::channel('scrub-test')->warning('Login failed with Bearer abc.def-123 at /reset?token=s3cret&x=1', [
        'password' => 'segredo-forte-1',
        'request' => [
            'phone' => '841234567',
            'motivations' => 'Servir a igreja',
            'birth_date' => '2001-02-03',
            'guardian_phone' => '849999999',
            'skills' => [['team_role_id' => 1]],
        ],
        'subscription' => ['endpoint' => 'https://push.example/abc', 'keys' => ['p256dh' => 'k', 'auth' => 'a']],
        'attempt' => 3,
    ]);

    $record = $this->handler->getRecords()[0];
    $logged = json_encode([$record->message, $record->context]);

    expect($logged)->not->toContain('segredo-forte-1')
        ->not->toContain('841234567')
        ->not->toContain('Servir a igreja')
        ->not->toContain('2001-02-03')
        ->not->toContain('849999999')
        ->not->toContain('abc.def-123')
        ->not->toContain('s3cret')
        ->not->toContain('push.example')
        ->and($record->context['attempt'])->toBe(3)
        ->and($record->context['request']['skills'])->toBe([['team_role_id' => 1]])
        ->and($record->context['password'])->toBe(ScrubSensitiveData::REDACTED);
});

test('a failed query is logged without its bound values', function () {
    $exception = new QueryException('sqlite', 'insert into users (phone) values (?)', ['841234567'], new PDOException('UNIQUE constraint failed: users.phone'));

    Log::channel('scrub-test')->error('Query failed', ['exception' => $exception]);

    $logged = $this->handler->getRecords()[0]->context['exception'];

    expect($logged->getMessage())->toContain('UNIQUE constraint failed')
        ->toContain('insert into users (phone) values (?)')
        ->not->toContain('841234567')
        ->and($logged->getFile())->toBe($exception->getFile());
});
