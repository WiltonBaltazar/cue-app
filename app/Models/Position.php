<?php

namespace App\Models;

use App\Enums\Level;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Places for one team role at one event, with an optional minimum level.
 *
 * @property int $id
 * @property int $event_id
 * @property int $team_role_id
 * @property int $quantity
 * @property Level|null $min_level
 * @property-read TeamRole $teamRole
 * @property-read Event $event
 */
class Position extends Model
{
    protected $fillable = ['event_id', 'team_role_id', 'quantity', 'min_level'];

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
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<TeamRole, $this>
     */
    public function teamRole(): BelongsTo
    {
        return $this->belongsTo(TeamRole::class);
    }
}
