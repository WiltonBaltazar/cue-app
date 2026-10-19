<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
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
        ];
    }

    /**
     * @return BelongsTo<Volunteer, $this>
     */
    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }
}
