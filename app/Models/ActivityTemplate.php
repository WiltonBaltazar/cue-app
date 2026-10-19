<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\Period;
use Carbon\CarbonImmutable;
use Database\Factories\ActivityTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Recurring activity, e.g. "Culto de Domingo" (US2). Times are local (Africa/Maputo).
 *
 * @property int $id
 * @property string $name
 * @property int $weekday ISO 1 (Monday) – 7 (Sunday)
 * @property int $interval_weeks
 * @property CarbonImmutable $starts_on
 * @property Period $period
 * @property string $arrival_time H:i:s
 * @property string $start_time H:i:s
 * @property string $end_time H:i:s
 * @property int $teardown_minutes
 * @property string $location
 * @property bool $is_active
 * @property-read Collection<int, TemplatePosition> $positions
 */
class ActivityTemplate extends Model
{
    /** @use HasFactory<ActivityTemplateFactory> */
    use HasFactory, LogsAuditActivity;

    protected $fillable = [
        'name', 'weekday', 'interval_weeks', 'starts_on', 'period', 'arrival_time',
        'start_time', 'end_time', 'teardown_minutes', 'location', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'interval_weeks' => 'integer',
            'starts_on' => 'immutable_date:Y-m-d',
            'period' => Period::class,
            'teardown_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<TemplatePosition, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(TemplatePosition::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Whether this template has an occurrence on the given local date.
     */
    public function occursOn(CarbonImmutable $date): bool
    {
        // Compare calendar dates only: mixing a Maputo-midnight date with a UTC-midnight
        // one would shift the week difference by the time-zone offset.
        $day = CarbonImmutable::parse($date->toDateString(), 'UTC');
        $start = CarbonImmutable::parse($this->starts_on->toDateString(), 'UTC');

        if ($day->dayOfWeekIso !== $this->weekday || $day->lt($start)) {
            return false;
        }

        $weeks = (int) $start->startOfWeek()->diffInWeeks($day->startOfWeek());

        return $weeks % max(1, $this->interval_weeks) === 0;
    }
}
