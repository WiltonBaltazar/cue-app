<?php

namespace App\Models;

use App\Enums\Level;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $activity_template_id
 * @property int $team_role_id
 * @property int $quantity
 * @property Level|null $min_level
 * @property-read TeamRole $teamRole
 */
class TemplatePosition extends Model
{
    protected $fillable = ['activity_template_id', 'team_role_id', 'quantity', 'min_level'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'min_level' => Level::class,
        ];
    }

    /**
     * @return BelongsTo<TeamRole, $this>
     */
    public function teamRole(): BelongsTo
    {
        return $this->belongsTo(TeamRole::class);
    }
}
