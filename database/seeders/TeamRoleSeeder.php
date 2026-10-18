<?php

namespace Database\Seeders;

use App\Models\TeamRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Pre-loads the service areas from the volunteer registration form (FR-007).
 */
class TeamRoleSeeder extends Seeder
{
    public const ROLES = [
        'Operação de câmara',
        'Realização e mistura de vídeo',
        'Relações com o público',
        'Informática',
        'Electrónica',
        'Cablagem',
        'Iluminação',
        'Áudio',
        'Transporte de equipamentos',
        'Montagem/desmontagem',
        'Streaming',
    ];

    public function run(): void
    {
        foreach (self::ROLES as $index => $name) {
            TeamRole::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $index + 1],
            );
        }
    }
}
