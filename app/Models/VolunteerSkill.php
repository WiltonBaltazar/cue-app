<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\Level;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A volunteer's interest, experience and level in one team role (FR-004).
 *
 * @property int $id
 * @property int $volunteer_id
 * @property int $team_role_id
 * @property bool $interested
 * @property bool $has_experience
 * @property Level $level
 * @property bool $is_preferred
 * @property-read TeamRole $teamRole
 */
class VolunteerSkill extends Model
{
    use LogsAuditActivity;

    protected $fillable = ['volunteer_id', 'team_role_id', 'interested', 'has_experience', 'level', 'is_preferred'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interested' => 'boolean',
            'has_experience' => 'boolean',
            'level' => Level::class,
            'is_preferred' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Volunteer, $this>
     */
    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }

    /**
     * @return BelongsTo<TeamRole, $this>
     */
    public function teamRole(): BelongsTo
    {
        return $this->belongsTo(TeamRole::class);
    }
}
