<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A published version of the consent declaration (FR-002). The latest row is current.
 *
 * @property int $id
 * @property int $version
 * @property string $body
 * @property int|null $published_by
 * @property CarbonImmutable $published_at
 */
class ConsentVersion extends Model
{
    use LogsAuditActivity;

    protected $fillable = ['version', 'body', 'published_by', 'published_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return self::query()->orderByDesc('version')->firstOrFail();
    }

    public static function currentNumber(): int
    {
        return (int) self::query()->max('version');
    }
}
