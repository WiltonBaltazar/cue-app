<?php

use App\Enums\AssignmentStatus;
use App\Enums\ScheduleStatus;
use App\Enums\VolunteerStatus;
use App\Models\Assignment;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Support\Facades\Notification;
use Tests\Support\ScheduleScenario;

// Phone-sized runs of the three flows that matter most (T129). They need a frontend
// build (`npm run build`) and Playwright's Chromium (`npx playwright install chromium`).

/** Playwright selector for the form control labelled with (part of) this text. */
function labelled(string $text, bool $exact = false): string
{
    return 'internal:label='.json_encode($text, JSON_UNESCAPED_UNICODE).($exact ? 's' : 'i');
}

/** Playwright selector for a button inside the open dialog. */
function dialogButton(string $name): string
{
    return 'internal:role=dialog >> internal:role=button[name='.json_encode($name, JSON_UNESCAPED_UNICODE).'s]';
}

beforeEach(function () {
    $this->withVite();
    Notification::fake();
    config(['services.turnstile.site_key' => null, 'services.turnstile.secret_key' => null]);
});

test('a volunteer registers on a phone', function () {
    $page = visit('/cadastro')->on()->mobile();

    $page->assertSee('Passo 1 de 9')
        ->fill(labelled('Nome completo'), 'Ana Machava')
        ->fill(labelled('Data de nascimento'), now()->subYears(25)->toDateString())
        ->press('Continuar')
        ->assertSee('Passo 2 de 9')
        ->fill(labelled('Número de telefone'), '841234567')
        ->fill('#password', 'segredo-forte-1')
        ->fill('#password_confirmation', 'segredo-forte-1')
        ->press('Continuar')
        ->assertSee('Passo 3 de 9')
        ->press('Continuar')
        ->assertSee('Passo 4 de 9')
        ->check(labelled('Operação de câmara', exact: true))
        ->press('Continuar')
        ->assertSee('Passo 5 de 9')
        ->check(labelled('Ainda não tenho experiência'))
        ->press('Continuar')
        ->assertSee('Passo 6 de 9')
        // The grid's checkboxes are visually hidden; the person taps the cell.
        ->click('label:has(input[aria-label="Domingo (Culto), Manhã"])')
        ->check(labelled('1 vez por mês', exact: true))
        ->check(labelled('Sim, regularmente', exact: true))
        ->press('Continuar')
        ->assertSee('Passo 7 de 9')
        ->press('Continuar')
        ->assertSee('Passo 8 de 9')
        ->press('Continuar')
        ->assertSee('Passo 9 de 9')
        ->check(labelled('Li e aceito a declaração'))
        ->press('Enviar cadastro')
        ->assertSee('Cadastro enviado')
        ->assertNoJavaScriptErrors();

    $volunteer = Volunteer::query()->sole();

    expect($volunteer->full_name)->toBe('Ana Machava')
        ->and($volunteer->status)->toBe(VolunteerStatus::Pending);
});

test('a volunteer declines a shift on a phone', function () {
    $coordinator = User::factory()->coordinator()->create();
    $scenario = ScheduleScenario::create();
    $volunteer = $scenario->volunteer();
    $this->actingAs($coordinator)->post(route('coord.assignments.store'), [
        'position_id' => $scenario->position->id,
        'volunteer_id' => $volunteer->id,
    ]);
    $this->actingAs($coordinator)->post(route('coord.schedules.publish', $scenario->schedule));
    $assignment = Assignment::query()->sole();

    $this->actingAs($volunteer->user);

    visit('/minha-escala')->on()->mobile()
        ->assertSee('Culto de Domingo')
        ->click('Culto de Domingo')
        ->press('Não posso ir')
        ->assertSee('Não pode ir?')
        ->click(dialogButton('Não posso ir'))
        ->assertSee('Ainda não tem turnos')
        ->assertNoJavaScriptErrors();

    expect($assignment->fresh()->status)->toBe(AssignmentStatus::Declined);
});

test('a coordinator publishes a schedule on a phone', function () {
    $coordinator = User::factory()->coordinator()->create();
    $scenario = ScheduleScenario::create();
    $scenario->volunteer();
    $this->actingAs($coordinator)->post(route('coord.schedules.generate', $scenario->schedule));

    $this->actingAs($coordinator);

    visit(route('coord.schedules.edit', $scenario->schedule, absolute: false))->on()->mobile()
        ->assertSee('Culto de Domingo')
        ->press('Publicar')
        ->assertSee('Publicar a escala?')
        ->click(dialogButton('Publicar'))
        ->assertNoJavaScriptErrors();

    expect($scenario->schedule->fresh()->status)->toBe(ScheduleStatus::Published);
});
