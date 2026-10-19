<?php

namespace Database\Seeders;

use App\Models\ConsentVersion;
use Illuminate\Database\Seeder;

/**
 * Version 1 of the consent declaration. A draft for the coordinators to review;
 * they publish changes from the app (FR-002), which creates a new version.
 */
class ConsentVersionSeeder extends Seeder
{
    public const BODY = <<<'TEXT'
        Declaro que as informações prestadas nesta ficha são verdadeiras.

        Autorizo a equipa da Rádio e Televisão Alfa & Ómega (RTVAO) da IEAD – Ministério Alfa & Ómega a guardar e usar estes dados apenas para organizar a minha participação como voluntário: cadastro, escalas, trocas, ausências e notificações.

        Sei que os meus dados pessoais (data de nascimento, escolaridade, compromissos, motivações e contactos) só são vistos pelos coordenadores e por mim, e que os colegas apenas vêem o meu primeiro nome, a inicial do apelido e a função nas escalas.

        Sei que posso consultar e corrigir os meus dados a qualquer momento, e pedir a sua remoção, ficando apenas registos anónimos de participação.
        TEXT;

    public function run(): void
    {
        ConsentVersion::query()->firstOrCreate(
            ['version' => 1],
            ['body' => self::BODY, 'published_at' => now()],
        );
    }
}
