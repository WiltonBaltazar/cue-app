<?php

use App\Enums\Frequency;
use App\Enums\Level;
use App\Enums\Period;
use App\Enums\VolunteerStatus;
use App\Http\Responses\GenericSpamResponder;
use App\Models\ConsentVersion;
use App\Models\RejectedRegistration;
use App\Models\TeamRole;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\RegistrationSubmitted;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * A complete, valid registration payload; override fields per test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationPayload(array $overrides = []): array
{
    $camera = TeamRole::query()->where('slug', 'operacao-de-camara')->firstOrFail();
    $audio = TeamRole::query()->where('slug', 'audio')->firstOrFail();

    return array_replace([
        'full_name' => 'Ana Maria Machava',
        'birth_date' => now()->subYears(25)->format('Y-m-d'),
        'phone' => '84 123 4567',
        'email' => 'ana@exemplo.com',
        'password' => 'senha-segura-2026',
        'password_confirmation' => 'senha-segura-2026',
        'neighbourhood' => 'Malhangalene',
        'education_level' => 'Licenciatura',
        'training' => 'Informática',
        'skills' => [
            ['team_role_id' => $camera->id, 'has_experience' => true],
            ['team_role_id' => $audio->id, 'has_experience' => false],
        ],
        'level' => Level::Intermediate->value,
        'preferred_role_id' => $camera->id,
        'can_support_others' => false,
        'availability' => [
            ['weekday' => 7, 'period' => Period::Morning->value],
            ['weekday' => 2, 'period' => Period::Evening->value],
        ],
        'max_frequency' => Frequency::TwoThreeMonthly->value,
        'variable_hours' => false,
        'available_for_training' => true,
        'commitments' => 'Coro aos sábados',
        'motivations' => 'Servir com os meus talentos.',
        'comments' => null,
        'consent_accepted' => true,
        'consent_version' => ConsentVersion::currentNumber(),
    ], $overrides);
}

test('the registration form shows the current declaration and team roles', function () {
    $this->get(route('registration.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registration/create')
            ->where('declaration.version', 1)
            ->has('teamRoles', 11));

    expect(route('registration.create', absolute: false))->toBe('/cadastro');
});

test('a complete registration creates a pending volunteer and notifies coordinators', function () {
    Notification::fake();
    $coordinator = User::factory()->coordinator()->create();

    $this->post(route('registration.store'), registrationPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('registration.submitted'));

    $user = User::query()->where('phone', '+258841234567')->firstOrFail();
    $volunteer = $user->volunteer;

    expect($user->isCoordinator())->toBeFalse()
        ->and($volunteer?->status)->toBe(VolunteerStatus::Pending)
        ->and($volunteer?->full_name)->toBe('Ana Maria Machava')
        ->and($volunteer?->consent_version)->toBe(1)
        ->and($volunteer?->consent_accepted_at)->not->toBeNull()
        ->and($volunteer?->is_minor)->toBeFalse()
        ->and($volunteer?->skills()->count())->toBe(2)
        ->and($volunteer?->skills->every(fn ($skill) => $skill->level === Level::Intermediate))->toBeTrue()
        ->and($volunteer?->skills->firstWhere('is_preferred', true)?->teamRole->slug)->toBe('operacao-de-camara')
        ->and($volunteer?->availabilities()->count())->toBe(2);

    $this->assertGuest();
    Notification::assertSentTo($coordinator, RegistrationSubmitted::class);
});

test('sensitive fields are stored encrypted', function () {
    $this->post(route('registration.store'), registrationPayload());

    $raw = DB::table('volunteers')->first();

    expect($raw->motivations)->not->toContain('Servir')
        ->and($raw->neighbourhood)->not->toBe('Malhangalene')
        ->and($raw->birth_date)->not->toContain('-');
});

test('registration is blocked without accepting the declaration', function () {
    $this->post(route('registration.store'), registrationPayload(['consent_accepted' => false]))
        ->assertSessionHasErrors('consent_accepted');

    expect(Volunteer::query()->count())->toBe(0);
});

test('registration is blocked when the declaration changed while filling the form', function () {
    ConsentVersion::query()->create(['version' => 2, 'body' => 'Nova declaração', 'published_at' => now()]);

    $this->post(route('registration.store'), registrationPayload(['consent_version' => 1]))
        ->assertSessionHasErrors('consent_version');
});

test('people under 14 can not register', function () {
    $this->post(route('registration.store'), registrationPayload([
        'birth_date' => now()->subYears(13)->format('Y-m-d'),
    ]))->assertSessionHasErrors('birth_date');
});

test('minors aged 14 to 17 must give a guardian name and phone', function () {
    $minor = ['birth_date' => now()->subYears(15)->format('Y-m-d')];

    $this->post(route('registration.store'), registrationPayload($minor))
        ->assertSessionHasErrors(['guardian_name', 'guardian_phone']);

    $this->post(route('registration.store'), registrationPayload([
        ...$minor,
        'guardian_name' => 'Maria Machava',
        'guardian_phone' => '82 765 4321',
    ]))->assertSessionHasNoErrors();

    expect(Volunteer::query()->first()?->is_minor)->toBeTrue();
});

test('a phone number that already has an account is blocked', function () {
    User::factory()->create(['phone' => '+258841234567']);

    $this->post(route('registration.store'), registrationPayload(['phone' => '841234567']))
        ->assertSessionHasErrors('phone');
});

test('invalid phone numbers are rejected', function () {
    $this->post(route('registration.store'), registrationPayload(['phone' => '123']))
        ->assertSessionHasErrors('phone');
});

test('a previously rejected and purged phone can register again and is flagged', function () {
    RejectedRegistration::query()->create([
        'phone_hash' => RejectedRegistration::hashPhone('+258841234567'),
        'rejected_at' => now()->subDays(40),
    ]);

    $this->post(route('registration.store'), registrationPayload())->assertSessionHasNoErrors();

    expect(Volunteer::query()->first()?->previously_rejected_at)->not->toBeNull();
});

test('at least one role and one availability slot are required', function () {
    $this->post(route('registration.store'), registrationPayload(['skills' => [], 'availability' => []]))
        ->assertSessionHasErrors(['skills', 'availability']);
});

test('a closed registration link shows the closed page and refuses submissions', function () {
    app(Settings::class)->set('registration_open', false);

    $this->get(route('registration.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('registration/closed'));

    $this->post(route('registration.store'), registrationPayload())->assertForbidden();

    expect(Volunteer::query()->count())->toBe(0);
});

test('registration is closed after the closing date', function () {
    app(Settings::class)->set('registration_closes_at', now()->subMinute()->toIso8601String());

    $this->get(route('registration.create'))
        ->assertInertia(fn ($page) => $page->component('registration/closed'));
});

test('submissions are limited to 5 per hour per origin', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('registration.store'), registrationPayload(['phone' => '123']));
    }

    $this->post(route('registration.store'), registrationPayload())->assertTooManyRequests();
});

test('submissions without the honeypot fields get a generic message', function () {
    config(['honeypot.enabled' => true]);

    $this->from(route('registration.create'))
        ->post(route('registration.store'), registrationPayload())
        ->assertSessionHasErrors(['form' => GenericSpamResponder::MESSAGE]);

    expect(Volunteer::query()->count())->toBe(0);
});
