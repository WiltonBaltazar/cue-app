<?php

namespace App\Models;

use App\Enums\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One weekday × period slot a volunteer is usually available.
 *
 * @property int $id
 * @property int $volunteer_id
 * @property int $weekday ISO 1 (Monday) – 7 (Sunday)
 * @property Period $period
 */
class Availability extends Model
{
    public const WEEKDAYS = [
        1 => 'Segunda',
        2 => 'Terça',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    protected $fillable = ['volunteer_id', 'weekday', 'period'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'period' => Period::class,
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
