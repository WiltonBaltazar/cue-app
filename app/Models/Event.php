<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\Period;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A concrete occurrence: generated from a template or created as a special event (US2).
 * Times are UTC; `date` is the local (Africa/Maputo) calendar date.
 *
 * @property int $id
 * @property int|null $activity_template_id
 * @property string $name
 * @property EventType $type
 * @property CarbonImmutable $date
 * @property Period $period
 * @property CarbonImmutable $arrival_at
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable $teardown_until
 * @property string $location
 * @property EventStatus $status
 * @property bool $is_customized
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancel_reason
 * @property-read ActivityTemplate|null $template
 * @property-read Collection<int, Position> $positions
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, LogsAuditActivity;

    protected $fillable = [
        'activity_template_id', 'name', 'type', 'date', 'period', 'arrival_at', 'starts_at',
        'ends_at', 'teardown_until', 'location',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'scheduled',
        'is_customized' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'date' => 'immutable_date:Y-m-d',
            'period' => Period::class,
            'arrival_at' => 'immutable_datetime',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'teardown_until' => 'immutable_datetime',
            'status' => EventStatus::class,
            'is_customized' => 'boolean',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditAttributes(): array
    {
        return [...array_values($this->getFillable()), 'status', 'is_customized', 'cancel_reason'];
    }

    /**
     * @return BelongsTo<ActivityTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ActivityTemplate::class, 'activity_template_id');
    }

    /**
     * @return HasMany<Position, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeScheduled(Builder $query): void
    {
        $query->where('status', EventStatus::Scheduled);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeBetweenDates(Builder $query, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $query->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }

    public function isCancelled(): bool
    {
        return $this->status === EventStatus::Cancelled;
    }

    public function requiredPeople(): int
    {
        return (int) $this->positions->sum('quantity');
    }
}
