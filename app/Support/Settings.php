<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Typed access to coordinator-editable settings, with the defaults from data-model.md.
 */
class Settings
{
    private const CACHE_KEY = 'settings.all';

    /**
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'event_horizon_months' => 3,
        'check_reminder_hours_before' => 72,
        'reminder_hours_before' => 24,
        'urgent_threshold_hours' => 48,
        'swap_auto_approve' => false,
        'registration_open' => true,
        'registration_closes_at' => null,
    ];

    public function eventHorizonMonths(): int
    {
        return (int) $this->get('event_horizon_months');
    }

    public function checkReminderHoursBefore(): int
    {
        return (int) $this->get('check_reminder_hours_before');
    }

    public function reminderHoursBefore(): int
    {
        return (int) $this->get('reminder_hours_before');
    }

    public function urgentThresholdHours(): int
    {
        return (int) $this->get('urgent_threshold_hours');
    }

    public function swapAutoApprove(): bool
    {
        return (bool) $this->get('swap_auto_approve');
    }

    public function registrationClosesAt(): ?CarbonImmutable
    {
        $value = $this->get('registration_closes_at');

        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }

    /**
     * Registration is open when switched on and the optional closing date has not passed (FR-001).
     */
    public function registrationIsOpen(): bool
    {
        $closesAt = $this->registrationClosesAt();

        return (bool) $this->get('registration_open')
            && ($closesAt === null || $closesAt->isFuture());
    }

    public function get(string $key): mixed
    {
        $this->assertKnown($key);

        $stored = $this->all();

        return array_key_exists($key, $stored) ? $stored[$key] : self::DEFAULTS[$key];
    }

    public function set(string $key, mixed $value): void
    {
        $this->assertKnown($key);

        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        /** @var array<string, mixed> */
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()
            ->pluck('value', 'key')
            ->all());
    }

    private function assertKnown(string $key): void
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            throw new InvalidArgumentException("Unknown setting [{$key}].");
        }
    }
}
