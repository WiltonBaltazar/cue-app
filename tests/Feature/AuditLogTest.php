<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AuditSanitizer;
use Spatie\Activitylog\Models\Activity;

test('role changes are audited with old and new values', function () {
    $coordinator = User::factory()->coordinator()->create();
    $user = User::factory()->volunteer()->create();

    $this->actingAs($coordinator);
    $user->forceFill(['role' => UserRole::Coordinator])->save();

    $activity = Activity::query()->where('subject_id', $user->id)->where('event', 'updated')->latest('id')->first();

    expect($activity?->log_name)->toBe('audit')
        ->and($activity?->causer_id)->toBe($coordinator->id)
        ->and($activity?->properties['attributes'])->toBe(['role' => 'coordinator'])
        ->and($activity?->properties['old'])->toBe(['role' => 'volunteer']);
});

test('personal fields are logged as changed without their values', function () {
    $user = User::factory()->create(['name' => 'Ana Machava', 'phone' => '+258841234567']);

    $user->update(['name' => 'Ana M. Machava', 'phone' => '+258851111111']);

    $activity = Activity::query()->where('subject_id', $user->id)->where('event', 'updated')->latest('id')->first();
    $json = json_encode($activity?->properties);

    expect($activity?->properties['attributes'])->toBe(['name' => AuditSanitizer::CHANGED, 'phone' => AuditSanitizer::CHANGED])
        ->and($json)->not->toContain('Machava')
        ->and($json)->not->toContain('841234567')
        ->and($json)->not->toContain('851111111');
});

test('creating a user logs no personal values', function () {
    $user = User::factory()->create(['name' => 'João Sitoe', 'email' => 'joao@exemplo.com']);

    $json = json_encode(Activity::query()->where('subject_id', $user->id)->where('event', 'created')->first()?->properties);

    expect($json)->not->toContain('Sitoe')
        ->and($json)->not->toContain('joao@exemplo.com');
});

test('secrets and routine updates are never logged', function () {
    $user = User::factory()->create();
    $before = Activity::query()->count();

    $user->update(['password' => 'nova-senha-2026']);
    $user->forceFill(['last_login_at' => now(), 'remember_token' => 'abc'])->save();

    expect(Activity::query()->count())->toBe($before);
});
