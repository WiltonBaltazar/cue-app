<?php

namespace App\Logging;

use App\Models\Volunteer;
use Illuminate\Database\QueryException;
use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;

/**
 * Log channel tap that keeps passwords, tokens and personal answers out of the logs
 * (Principle I, research R16). Values under a sensitive key in the context or extra
 * data are replaced, at any depth; bearer tokens and URL tokens are masked in messages.
 */
class ScrubSensitiveData
{
    public const REDACTED = '[removido]';

    /**
     * Keys that are always secret or personal; the volunteer's 🔒 fields are added from
     * the model, so a new encrypted field is scrubbed without touching this list.
     */
    private const KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        'token', '_token', 'remember_token', 'api_token', 'access_token', 'refresh_token',
        'secret', 'secret_key', 'client_secret', 'api_key', 'private_key', 'auth', 'keys', 'p256dh',
        'authorization', 'cookie', 'set-cookie', 'x-csrf-token', 'x-xsrf-token',
        'two_factor_secret', 'two_factor_recovery_codes', 'code', 'recovery_code',
        'cf-turnstile-response', 'endpoint',
        'phone', 'phone_hash', 'email', 'full_name', 'name', 'reason',
    ];

    /** @var array<string, true>|null */
    private static ?array $keys = null;

    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if (! $monolog instanceof Monolog) {
            return;
        }

        $monolog->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
            message: self::scrubMessage($record->message),
            context: self::scrub($record->context),
            extra: self::scrub($record->extra),
        ));
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof QueryException) {
                $data[$key] = self::withoutBindings($value);
            } elseif (is_string($key) && isset(self::keys()[strtolower($key)])) {
                $data[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $data[$key] = self::scrub($value);
            } elseif (is_string($value)) {
                $data[$key] = self::scrubMessage($value);
            }
        }

        return $data;
    }

    /**
     * A query error's message lists the bound values (phones, encrypted answers…);
     * keep the SQL with placeholders, the driver error and the trace, not the values.
     */
    private static function withoutBindings(QueryException $exception): \RuntimeException
    {
        $driverError = $exception->getPrevious()?->getMessage() ?? 'Database error';

        $copy = new \RuntimeException(sprintf(
            '%s: %s (Connection: %s, SQL: %s)',
            QueryException::class,
            $driverError,
            $exception->connectionName,
            $exception->getSql(),
        ), (int) $exception->getCode());

        (new \ReflectionProperty(\Exception::class, 'file'))->setValue($copy, $exception->getFile());
        (new \ReflectionProperty(\Exception::class, 'line'))->setValue($copy, $exception->getLine());
        (new \ReflectionProperty(\Exception::class, 'trace'))->setValue($copy, $exception->getTrace());

        return $copy;
    }

    public static function scrubMessage(string $message): string
    {
        return (string) preg_replace(
            [
                '/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]+/i',
                '/([?&](?:token|signature|password|code)=)[^&\s"\']+/i',
            ],
            ['$1 '.self::REDACTED, '$1'.self::REDACTED],
            $message,
        );
    }

    /**
     * @return array<string, true>
     */
    private static function keys(): array
    {
        if (self::$keys === null) {
            $encrypted = array_keys(array_filter(
                (new Volunteer)->getCasts(),
                fn (string $cast) => str_starts_with($cast, 'encrypted'),
            ));

            self::$keys = array_fill_keys(array_merge(self::KEYS, $encrypted), true);
        }

        return self::$keys;
    }
}
