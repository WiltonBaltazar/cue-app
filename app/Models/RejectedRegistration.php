<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Remembers that a phone number was once rejected, without storing the number (FR-003a).
 * Never blocks a new registration; it only informs the coordinator.
 *
 * @property int $id
 * @property string $phone_hash
 * @property CarbonImmutable $rejected_at
 */
class RejectedRegistration extends Model
{
    protected $fillable = ['phone_hash', 'rejected_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * Keyed hash so the stored value cannot be reversed by trying every phone number.
     */
    public static function hashPhone(string $e164Phone): string
    {
        return hash_hmac('sha256', $e164Phone, (string) config('app.key'));
    }

    public static function lastRejectionFor(string $e164Phone): ?CarbonImmutable
    {
        return self::query()
            ->where('phone_hash', self::hashPhone($e164Phone))
            ->latest('rejected_at')
            ->value('rejected_at');
    }
}
