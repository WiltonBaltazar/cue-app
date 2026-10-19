<?php

namespace Database\Seeders;

use App\Actions\Events\GenerateOccurrences;
use App\Enums\Level;
use App\Enums\Period;
use App\Models\ActivityTemplate;
use App\Models\TeamRole;
use App\Support\LocalTime;
use Illuminate\Database\Seeder;

/**
 * The two base activities from the spec assumptions. Safe to run once in production:
 * `php artisan db:seed --class=ActivityTemplateSeeder`.
 */
class ActivityTemplateSeeder extends Seeder
{
    public function run(GenerateOccurrences $generateOccurrences): void
    {
        $role = fn (string $slug): TeamRole => TeamRole::query()->where('slug', $slug)->firstOrFail();

        $templates = [
            [
                'attributes' => [
                    'name' => 'Culto de Domingo',
                    'weekday' => 7,
                    'period' => Period::Morning,
                    'arrival_time' => '07:30',
                    'start_time' => '08:30',
                    'end_time' => '11:30',
                    'teardown_minutes' => 30,
                ],
                'positions' => [
                    ['operacao-de-camara', 3, null],
                    ['realizacao-e-mistura-de-video', 1, Level::Intermediate],
                    ['audio', 1, Level::Intermediate],
                    ['streaming', 1, null],
                ],
            ],
            [
                'attributes' => [
                    'name' => 'Estudo Bíblico de Terça',
                    'weekday' => 2,
                    'period' => Period::Evening,
                    'arrival_time' => '17:30',
                    'start_time' => '18:30',
                    'end_time' => '20:00',
                    'teardown_minutes' => 15,
                ],
                'positions' => [
                    ['operacao-de-camara', 1, null],
                    ['audio', 1, null],
                    ['streaming', 1, null],
                ],
            ],
        ];

        foreach ($templates as $definition) {
            $template = ActivityTemplate::query()->firstOrCreate(
                ['name' => $definition['attributes']['name']],
                [
                    ...$definition['attributes'],
                    'interval_weeks' => 1,
                    'starts_on' => LocalTime::today()->toDateString(),
                    'location' => 'Templo sede',
                    'is_active' => true,
                ],
            );

            if ($template->wasRecentlyCreated) {
                foreach ($definition['positions'] as [$slug, $quantity, $minLevel]) {
                    $template->positions()->create([
                        'team_role_id' => $role($slug)->id,
                        'quantity' => $quantity,
                        'min_level' => $minLevel,
                    ]);
                }
            }
        }

        $generateOccurrences->handle();
    }
}
