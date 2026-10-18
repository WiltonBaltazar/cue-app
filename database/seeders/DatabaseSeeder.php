<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(TeamRoleSeeder::class);

        if (! app()->environment('local')) {
            return;
        }

        // Local-only accounts (password "password"). The coordinator has no 2FA yet,
        // so the first login walks through the mandatory two-factor setup.
        User::factory()->create([
            'name' => 'Coordenador Local',
            'phone' => '+258840000001',
            'email' => 'coordenador@cue.test',
            'role' => UserRole::Coordinator,
        ]);

        User::factory()->create([
            'name' => 'Voluntária Local',
            'phone' => '+258840000002',
            'email' => null,
            'role' => UserRole::Volunteer,
        ]);
    }
}
