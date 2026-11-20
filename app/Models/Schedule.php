<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\ScheduleStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A schedule ("Escala") for a date range, or for one event only (event_id): draft until
 * published (FR-014). Range schedules leave out events that have their own schedule.
 *
 * @property int $id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property int|null $event_id
 * @property ScheduleStatus $status
 * @property int|null $created_by
 * @property int|null $published_by
 * @property CarbonImmutable|null $published_at
 * @property list<array{positionId: int, eventId: int, label: string, missing: int, suggestions: list<array{volunteerId: int, violations: list<string>}>}>|null $generation_report see GapReport::toArray()
 */
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory, LogsAuditActivity;

    protected $fillable = ['starts_on', 'ends_on', 'event_id', 'created_by'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date:Y-m-d',
            'ends_on' => 'immutable_date:Y-m-d',
            'status' => ScheduleStatus::class,
            'published_at' => 'immutable_datetime',
            'generation_report' => 'array',
        ];
    }

    /**
     * The report is a computed snapshot; only the status changes are worth auditing.
     *
     * @return list<string>
     */
    protected function auditAttributes(): array
    {
        return ['starts_on', 'ends_on', 'status', 'published_by'];
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * The one event of a single-event schedule.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isForSingleEvent(): bool
    {
        return $this->event_id !== null;
    }

    /**
     * Events covered: the single event, or the range's events without a schedule of their own.
     *
     * @return Builder<Event>
     */
    public function events(): Builder
    {
        if ($this->isForSingleEvent()) {
            return Event::query()->whereKey($this->event_id);
        }

        return Event::query()
            ->betweenDates($this->starts_on, $this->ends_on)
            ->whereNotIn('id', self::query()->whereNotNull('event_id')->select('event_id'));
    }

    /**
     * The schedule an event belongs to: its own one first, otherwise the range covering it.
     */
    public static function forEvent(Event $event): ?self
    {
        $date = $event->date->toDateString();

        return self::query()->where('event_id', $event->id)->first()
            ?? self::query()
                ->whereNull('event_id')
                ->where('starts_on', '<=', $date)
                ->where('ends_on', '>=', $date)
                ->first();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForRange(Builder $query): void
    {
        $query->whereNull('event_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ScheduleStatus::Published);
    }

    public function isPublished(): bool
    {
        return $this->status === ScheduleStatus::Published;
    }
}
