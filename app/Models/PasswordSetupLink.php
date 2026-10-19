<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property int|null $issued_by
 * @property CarbonImmutable $expires_at
 * @property-read User $user
 */
class PasswordSetupLink extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'issued_by', 'expires_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findValid(string $token): ?self
    {
        return self::query()
            ->where('token_hash', self::hashToken($token))
            ->where('expires_at', '>', now())
            ->first();
    }
}
