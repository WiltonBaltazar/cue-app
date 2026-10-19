<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\SwapStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to hand a shift to a colleague (US4, FR-017, FR-018). Directed when
 * `target_volunteer_id` is set, otherwise open to every eligible colleague.
 *
 * @property int $id
 * @property int $assignment_id
 * @property int $requester_id
 * @property int|null $target_volunteer_id
 * @property int|null $accepted_by_id
 * @property SwapStatus $status
 * @property bool $is_urgent
 * @property bool $auto_approved
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable|null $created_at
 * @property-read Assignment $assignment
 * @property-read Volunteer $requester
 * @property-read Volunteer|null $target
 * @property-read Volunteer|null $acceptedBy
 */
class SwapRequest extends Model
{
    use LogsAuditActivity;

    protected $fillable = ['assignment_id', 'requester_id', 'target_volunteer_id', 'is_urgent'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SwapStatus::class,
            'is_urgent' => 'boolean',
            'auto_approved' => 'boolean',
            'decided_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditAttributes(): array
    {
        return ['assignment_id', 'requester_id', 'target_volunteer_id', 'accepted_by_id', 'status', 'auto_approved', 'decided_by'];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * @return BelongsTo<Volunteer, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class, 'requester_id');
    }

    /**
     * @return BelongsTo<Volunteer, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class, 'target_volunteer_id');
    }

    /**
     * @return BelongsTo<Volunteer, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class, 'accepted_by_id');
    }

    /**
     * Still waiting for a colleague or a coordinator.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [SwapStatus::Open, SwapStatus::Accepted]);
    }
}
