<?php

use App\Enums\Frequency;
use App\Enums\Level;
use App\Enums\Period;
use App\Enums\TrainingAvailability;
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
        'nickname' => 'Aninha',
        'birth_date' => now()->subYears(25)->format('Y-m-d'),
        'phone' => '84 123 4567',
        'phone_has_whatsapp' => true,
        'email' => 'ana@exemplo.com',
        'social_media' => [['network' => 'Instagram', 'handle' => '@ana.machava']],
        'password' => 'senha-segura-2026',
        'password_confirmation' => 'senha-segura-2026',
        'neighbourhood' => 'Malhangalene',
        'education_level' => 'Ensino superior',
        'training' => 'Curso de informática',
        'skills' => [
            ['team_role_id' => $camera->id],
            ['team_role_id' => $audio->id],
        ],
        'preferred_role_id' => $camera->id,
        'other_area' => null,
        'experience_role_ids' => [$camera->id],
        'other_experience' => 'Fotografia',
        'no_experience' => false,
        'experience_description' => 'Filmo casamentos há 3 anos com uma Sony A7.',
        'level' => Level::Intermediate->value,
        'availability' => [
            ['weekday' => 7, 'period' => Period::Morning->value],
            ['weekday' => 2, 'period' => Period::Evening->value],
        ],
        'other_days_by_arrangement' => false,
        'max_frequency' => Frequency::TwoThreeMonthly->value,
        'frequency_note' => null,
        'variable_hours' => false,
        'training_availability' => TrainingAvailability::WithNotice->value,
        'commitments' => 'Coro aos sábados',
        'motivations' => 'Servir com os meus talentos.',
        'expectations' => 'Aprender realização.',
        'goals' => 'Transmitir os cultos em HD.',
        'comments' => null,
        'suggestions' => 'Fazer ensaios técnicos mensais.',
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
        ->and($volunteer?->availabilities()->count())->toBe(2)
        ->and($volunteer?->nickname)->toBe('Aninha')
        ->and($volunteer?->phone_has_whatsapp)->toBeTrue()
        ->and($volunteer?->training_availability)->toBe(TrainingAvailability::WithNotice)
        ->and($volunteer?->can_support_others)->toBeFalse()
        ->and($volunteer?->suggestions)->toBe('Fazer ensaios técnicos mensais.');

    $this->assertGuest();
    Notification::assertSentTo($coordinator, RegistrationSubmitted::class);
});

test('a different WhatsApp number and several social media profiles can be given', function () {
    $this->post(route('registration.store'), registrationPayload([
        'phone_has_whatsapp' => false,
        'whatsapp_phone' => '86 765 4321',
        'social_media' => [
            ['network' => 'Instagram', 'handle' => '@ana.machava'],
            ['network' => 'Facebook', 'handle' => 'Ana Machava'],
        ],
    ]))->assertSessionHasNoErrors();

    $volunteer = Volunteer::query()->firstOrFail();
    $raw = DB::table('volunteers')->first();

    expect($volunteer->whatsapp_phone)->toBe('+258867654321')
        ->and($volunteer->social_media)->toBe([
            ['network' => 'Instagram', 'handle' => '@ana.machava'],
            ['network' => 'Facebook', 'handle' => 'Ana Machava'],
        ])
        ->and($raw->whatsapp_phone)->not->toContain('258')
        ->and($raw->social_media)->not->toContain('ana.machava');
});

test('the WhatsApp number is ignored when it is the login phone, and must be valid otherwise', function () {
    $this->post(route('registration.store'), registrationPayload([
        'phone_has_whatsapp' => false,
        'whatsapp_phone' => '123',
    ]))->assertSessionHasErrors('whatsapp_phone');

    $this->post(route('registration.store'), registrationPayload([
        'phone_has_whatsapp' => true,
        'whatsapp_phone' => '86 765 4321',
    ]))->assertSessionHasNoErrors();

    expect(Volunteer::query()->firstOrFail()->whatsapp_phone)->toBeNull();
});

test('each social media profile needs a known network and a handle', function () {
    $this->post(route('registration.store'), registrationPayload([
        'social_media' => [['network' => 'MySpace', 'handle' => '']],
    ]))->assertSessionHasErrors(['social_media.0.network', 'social_media.0.handle']);
});

test('experience is recorded apart from the areas the person wants to serve in', function () {
    $camera = TeamRole::query()->where('slug', 'operacao-de-camara')->firstOrFail();
    $audio = TeamRole::query()->where('slug', 'audio')->firstOrFail();
    $lighting = TeamRole::query()->where('slug', 'iluminacao')->firstOrFail();

    $this->post(route('registration.store'), registrationPayload([
        'experience_role_ids' => [$audio->id, $lighting->id],
    ]))->assertSessionHasNoErrors();

    $volunteer = Volunteer::query()->firstOrFail();

    expect($volunteer->experience_role_ids)->toBe([$audio->id, $lighting->id])
        ->and($volunteer->skills()->pluck('team_role_id')->all())->toEqualCanonicalizing([$camera->id, $audio->id])
        ->and($volunteer->skills()->where('has_experience', true)->pluck('team_role_id')->all())->toBe([$audio->id]);
});

test('experience must be given, or the person must say they have none yet', function () {
    $this->post(route('registration.store'), registrationPayload([
        'experience_role_ids' => [],
        'other_experience' => null,
    ]))->assertSessionHasErrors('experience_role_ids');

    $this->post(route('registration.store'), registrationPayload([
        'experience_role_ids' => [],
        'other_experience' => null,
        'no_experience' => true,
        'level' => Level::Beginner->value,
    ]))->assertSessionHasNoErrors();
});

test('choosing the top level means the volunteer can support others', function () {
    $this->post(route('registration.store'), registrationPayload(['level' => Level::Mentor->value]))
        ->assertSessionHasNoErrors();

    expect(Volunteer::query()->firstOrFail()->can_support_others)->toBeTrue();
});

test('variable hours or other days by arrangement replace the availability grid', function () {
    $this->post(route('registration.store'), registrationPayload(['availability' => []]))
        ->assertSessionHasErrors('availability');

    $this->post(route('registration.store'), registrationPayload([
        'availability' => [],
        'other_days_by_arrangement' => true,
    ]))->assertSessionHasNoErrors();

    expect(Volunteer::query()->firstOrFail()->other_days_by_arrangement)->toBeTrue();
});

test('sensitive fields are stored encrypted', function () {
    $this->post(route('registration.store'), registrationPayload());

    $raw = DB::table('volunteers')->first();

    expect($raw->motivations)->not->toContain('Servir')
        ->and($raw->neighbourhood)->not->toBe('Malhangalene')
        ->and($raw->birth_date)->not->toContain('-')
        ->and($raw->experience_description)->not->toContain('Sony')
        ->and($raw->suggestions)->not->toContain('ensaios');
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
