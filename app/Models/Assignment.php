<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\AssignmentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shift ("Turno"): one volunteer in one position at one event (FR-014).
 *
 * @property int $id
 * @property int $schedule_id
 * @property int $position_id
 * @property int $event_id
 * @property int $volunteer_id
 * @property AssignmentStatus $status
 * @property string $source auto|manual|swap
 * @property bool $is_locked
 * @property string|null $override_note
 * @property CarbonImmutable|null $conflict_flagged_at
 * @property CarbonImmutable|null $responded_at
 * @property CarbonImmutable|null $check_sent_at
 * @property CarbonImmutable|null $reminder_sent_at
 * @property int $lock_version
 * @property int|null $updated_by
 * @property-read Schedule $schedule
 * @property-read Position $position
 * @property-read Event $event
 * @property-read Volunteer $volunteer
 * @property-read User|null $updater
 */
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory, LogsAuditActivity;

    public const SOURCE_AUTO = 'auto';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SWAP = 'swap';

    protected $fillable = ['schedule_id', 'position_id', 'event_id', 'volunteer_id', 'source', 'is_locked', 'override_note'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'confirmed',
        'lock_version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'is_locked' => 'boolean',
            'conflict_flagged_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
            'check_sent_at' => 'immutable_datetime',
            'reminder_sent_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Optimistic concurrency (FR-013): every change bumps the version.
        static::updating(function (self $assignment): void {
            $assignment->lock_version = $assignment->getOriginal('lock_version') + 1;

            // A new person in the place (manual change or swap) gets their own check and reminder.
            if ($assignment->isDirty('volunteer_id')) {
                $assignment->check_sent_at = null;
                $assignment->reminder_sent_at = null;
            }
        });
    }

    /**
     * @return list<string>
     */
    protected function auditAttributes(): array
    {
        return ['schedule_id', 'position_id', 'event_id', 'volunteer_id', 'status', 'source', 'is_locked', 'override_note', 'conflict_flagged_at'];
    }

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Volunteer, $this>
     */
    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Shifts that occupy the volunteer (count for rules and coverage).
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', AssignmentStatus::Confirmed);
    }
}
