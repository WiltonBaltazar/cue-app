<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\ScheduleStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A schedule ("Escala") for a date range: draft until published (FR-014).
 *
 * @property int $id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
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

    protected $fillable = ['starts_on', 'ends_on', 'created_by'];

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
     * Events covered by this schedule's dates.
     *
     * @return Builder<Event>
     */
    public function events(): Builder
    {
        return Event::query()->betweenDates($this->starts_on, $this->ends_on);
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
