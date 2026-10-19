<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('coordinators:create {phone? : Telefone, ex. 84 123 4567} {--name= : Nome}')]
#[Description('Create the first coordinator account, or make an existing account a coordinator')]
class CreateCoordinator extends Command
{
    public function handle(): int
    {
        $phone = Phone::normalize((string) ($this->argument('phone') ?? $this->ask('Telefone')));

        if ($phone === null) {
            $this->error('Número de telefone inválido.');

            return self::FAILURE;
        }

        $user = User::query()->where('phone', $phone)->first();

        if ($user !== null) {
            if ($user->role === UserRole::Coordinator) {
                $this->info("{$phone} já é coordenador.");

                return self::SUCCESS;
            }

            if (! $this->confirm("{$phone} já tem conta ({$user->name}). Tornar coordenador?")) {
                return self::FAILURE;
            }
        } else {
            $name = (string) ($this->option('name') ?: $this->ask('Nome'));
            $password = (string) $this->secret('Palavra-passe (mínimo 8 caracteres)');

            if (trim($name) === '' || mb_strlen($password) < 8) {
                $this->error('Indique o nome e uma palavra-passe com pelo menos 8 caracteres.');

                return self::FAILURE;
            }

            $user = new User(['name' => $name, 'phone' => $phone, 'password' => $password]);
        }

        $user->role = UserRole::Coordinator;
        $user->save();

        $this->info("Coordenador {$user->name} ({$phone}) pronto. Entre com este telefone.");

        return self::SUCCESS;
    }
}
