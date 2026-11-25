<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A period a volunteer cannot serve (FR-016). The reason is sensitive (FR-026).
 *
 * @property int $id
 * @property int $volunteer_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property string|null $reason
 * @property int|null $created_by
 */
class Absence extends Model
{
    use LogsAuditActivity;

    protected $fillable = ['volunteer_id', 'starts_on', 'ends_on', 'reason', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date:Y-m-d',
            'ends_on' => 'immutable_date:Y-m-d',
            'reason' => 'encrypted',
            'created_by' => 'integer',
        ];
    }

    /**
     * Registered by someone other than the volunteer.
     */
    public function isByCoordinator(Volunteer $volunteer): bool
    {
        return $this->created_by !== null && $this->created_by !== $volunteer->user_id;
    }

    /**
     * Coordinators take back only what coordinators registered, and only until it is over.
     */
    public function isRemovableByCoordinator(Volunteer $volunteer): bool
    {
        return $this->isByCoordinator($volunteer) && $this->ends_on->toDateString() >= LocalTime::today()->toDateString();
    }

    /**
     * @return BelongsTo<Volunteer, $this>
     */
    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }
}
