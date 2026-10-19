<?php

namespace Database\Seeders;

use App\Enums\Frequency;
use App\Enums\Level;
use App\Enums\Period;
use App\Models\TeamRole;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Database\Seeder;

/**
 * A local demo team big enough to fill every role of the recurring activities (Sunday
 * service, Tuesday study) with rotation to spare, plus a few people for every other role.
 * Safe to run again: each person has a fixed phone number and is skipped if present.
 * Password "password". `php artisan db:seed --class=DemoTeamSeeder`.
 */
class DemoTeamSeeder extends Seeder
{
    /**
     * People per role (by slug); the default covers roles no activity uses yet.
     *
     * @var array<string, int>
     */
    private const TEAM_SIZE = [
        'operacao-de-camara' => 9,
        'realizacao-e-mistura-de-video' => 5,
        'audio' => 6,
        'streaming' => 6,
    ];

    private const DEFAULT_TEAM_SIZE = 4;

    private const FIRST_NAMES = [
        'Ana', 'Benedito', 'Celeste', 'Dércio', 'Esperança', 'Filipe', 'Graça', 'Hélder',
        'Inês', 'Joaquim', 'Lurdes', 'Manuel', 'Nádia', 'Osvaldo', 'Paulina', 'Quitéria',
        'Rafael', 'Sónia', 'Tomás', 'Uneia', 'Vasco', 'Xavier', 'Yolanda', 'Zacarias',
        'Albertina', 'Bento', 'Cláudia', 'Domingos', 'Edson', 'Fátima', 'Gervásio', 'Hortência',
    ];

    private const SURNAMES = [
        'Machava', 'Sitoe', 'Cossa', 'Mondlane', 'Nhantumbo', 'Macuácua', 'Chissano', 'Bila',
        'Tembe', 'Matsinhe', 'Langa', 'Mabunda', 'Muianga', 'Zandamela', 'Cumbe', 'Novela',
        'Mucavele', 'Chambal', 'Manhiça', 'Sambo',
    ];

    public function run(): void
    {
        $roles = TeamRole::query()->active()->ordered()->get();
        $index = 0;

        foreach ($roles as $roleNumber => $role) {
            $size = self::TEAM_SIZE[$role->slug] ?? self::DEFAULT_TEAM_SIZE;

            for ($member = 0; $member < $size; $member++, $index++) {
                // Every other person also helps in the next role, as a beginner.
                $secondRole = $member % 2 === 0 ? $roles[($roleNumber + 1) % $roles->count()] : null;

                $this->person($index, $role, $this->level($member), $secondRole);
            }
        }
    }

    private function person(int $index, TeamRole $role, Level $level, ?TeamRole $secondRole): void
    {
        $phone = '+25884100'.str_pad((string) $index, 4, '0', STR_PAD_LEFT);

        if (User::query()->where('phone', $phone)->exists()) {
            return;
        }

        // 32 first names × a coprime step through 20 surnames: no repeated full name.
        $name = self::FIRST_NAMES[$index % count(self::FIRST_NAMES)].' '
            .self::SURNAMES[($index * 7 + intdiv($index, count(self::FIRST_NAMES))) % count(self::SURNAMES)];

        $user = User::factory()->volunteer()->create([
            'name' => $name,
            'phone' => $phone,
            'email' => null,
        ]);

        $factory = Volunteer::factory()
            ->for($user)
            ->active()
            ->withSkill($role, $level, preferred: true)
            ->availableOn($this->availability($index));

        if ($secondRole !== null) {
            $factory = $factory->withSkill($secondRole, Level::Beginner);
        }

        $factory->create([
            'full_name' => $name,
            'max_frequency' => [Frequency::Weekly, Frequency::TwoThreeMonthly, Frequency::TwoThreeMonthly, Frequency::Monthly][$index % 4],
            'can_support_others' => $level === Level::Advanced || $level === Level::Mentor,
        ]);
    }

    /**
     * At least half of each team is intermediate or better, for positions with a minimum.
     */
    private function level(int $member): Level
    {
        return [Level::Intermediate, Level::Advanced, Level::Beginner, Level::Intermediate, Level::Mentor, Level::Beginner][$member % 6];
    }

    /**
     * Everyone can do Sunday morning; most also Tuesday evening; some Saturday afternoon.
     *
     * @return list<array{int, Period}>
     */
    private function availability(int $index): array
    {
        $slots = [[7, Period::Morning]];

        if ($index % 3 !== 2) {
            $slots[] = [2, Period::Evening];
        }

        if ($index % 4 === 0) {
            $slots[] = [6, Period::Afternoon];
        }

        return $slots;
    }
}
