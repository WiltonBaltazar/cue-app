<?php

namespace Database\Seeders;

use App\Enums\Frequency;
use App\Enums\Level;
use App\Enums\Period;
use App\Enums\UserRole;
use App\Models\TeamRole;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Database\Seeder;

/**
 * Model events stay on: Volunteer derives birth_year/is_minor from the encrypted
 * birth date when saving.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reference data every environment needs.
        $this->call([TeamRoleSeeder::class, ConsentVersionSeeder::class]);

        if (! app()->environment('local')) {
            return;
        }

        $this->seedLocalAccounts();
        $this->seedDemoVolunteers();
        $this->call(ActivityTemplateSeeder::class);
    }

    /**
     * Local-only accounts (password "password"). Two-factor login is optional and
     * can be switched on in Perfil → Segurança.
     */
    private function seedLocalAccounts(): void
    {
        User::factory()->create([
            'name' => 'Coordenador Local',
            'phone' => '+258840000001',
            'email' => 'coordenador@cue.test',
            'role' => UserRole::Coordinator,
        ]);

        $volunteerUser = User::factory()->create([
            'name' => 'Voluntária Local',
            'phone' => '+258840000002',
            'email' => null,
            'role' => UserRole::Volunteer,
        ]);

        Volunteer::factory()
            ->for($volunteerUser)
            ->active()
            ->withSkill($this->role('audio'), Level::Intermediate, preferred: true)
            ->withSkill($this->role('streaming'), Level::Beginner)
            ->availableOn([[7, Period::Morning], [2, Period::Evening]])
            ->create(['full_name' => 'Voluntária Local']);
    }

    /**
     * A small team to try reviews and, later, schedule generation.
     */
    private function seedDemoVolunteers(): void
    {
        $roles = TeamRole::query()->ordered()->get()->all();
        $count = count($roles);

        foreach (range(1, 10) as $index) {
            Volunteer::factory()
                ->active()
                ->withSkill($roles[$index % $count], Level::cases()[$index % 4], preferred: true)
                ->withSkill($roles[($index + 3) % $count], Level::Beginner)
                ->availableOn([[7, Period::Morning], [($index % 6) + 1, Period::Evening]])
                ->create(['max_frequency' => Frequency::cases()[$index % 3]]);
        }

        // Registrations waiting for review, including a minor without guardian consent yet.
        Volunteer::factory()->count(2)->pending()
            ->withSkill($this->role('operacao-de-camara'))
            ->availableOn([[7, Period::Morning]])
            ->create();

        Volunteer::factory()->minor()->pending()
            ->withSkill($this->role('iluminacao'))
            ->availableOn([[6, Period::Afternoon]])
            ->create();
    }

    private function role(string $slug): TeamRole
    {
        return TeamRole::query()->where('slug', $slug)->firstOrFail();
    }
}
