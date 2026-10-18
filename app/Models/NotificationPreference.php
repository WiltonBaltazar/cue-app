<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user choice of which notification types arrive as push (FR-025). In-app
 * notifications are always kept, whatever the preference (FR-040).
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property bool $push
 */
class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'type', 'push'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'push' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
