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

    /** Regular service on that weekday, shown beside the day on the availability grid. */
    public const WEEKDAY_SERVICES = [
        2 => 'Estudo Bíblico',
        7 => 'Culto',
    ];

    /**
     * Weekday options for the availability grid.
     *
     * @return list<array{value: int, label: string, hint: string|null}>
     */
    public static function weekdayOptions(): array
    {
        return array_map(
            fn (int $day, string $label) => ['value' => $day, 'label' => $label, 'hint' => self::WEEKDAY_SERVICES[$day] ?? null],
            array_keys(self::WEEKDAYS),
            self::WEEKDAYS,
        );
    }

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
