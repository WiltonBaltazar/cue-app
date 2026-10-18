<?php

use App\Http\Responses\NeutralPasswordResetLinkResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/*
| FR-006 and FR-032: login throttling, neutral password-reset answers and
| session management. Session tests use the database driver, as in production.
*/

function seedSession(User $user, string $id): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/130.0',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->getTimestamp(),
    ]);
}

test('login is limited to 5 attempts per minute per phone and origin, whatever the format', function () {
    $user = User::factory()->create(['phone' => '+258841234567']);

    foreach (['841234567', '84 123 4567', '+258841234567', '258841234567', '841234567'] as $typed) {
        $this->post(route('login.store'), ['phone' => $typed, 'password' => 'wrong']);
    }

    $this->post(route('login.store'), ['phone' => $user->phone, 'password' => 'password'])
        ->assertTooManyRequests();

    $this->assertGuest();
    RateLimiter::clear('+258841234567|127.0.0.1');
});

test('password reset gives the same answer for known and unknown e-mails', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ana@exemplo.com']);

    $known = $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => $user->email]);
    $unknown = $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'ninguem@exemplo.com']);

    foreach ([$known, $unknown] as $response) {
        $response->assertRedirect(route('password.request'))
            ->assertSessionHas('status', NeutralPasswordResetLinkResponse::MESSAGE)
            ->assertSessionHasNoErrors();
    }
});

describe('sessions', function () {
    beforeEach(function () {
        config(['session.driver' => 'database']);
    });

    test('users see only their own sessions', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        seedSession($user, 'mine-phone');
        seedSession($other, 'not-mine');

        $this->actingAs($user)
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/sessions')
                ->has('sessions', fn ($sessions) => $sessions->each(
                    fn ($session) => $session->whereNot('id', 'not-mine')->etc(),
                )));
    });

    test('a session on another device can be ended', function () {
        $user = User::factory()->create();
        seedSession($user, 'old-phone');

        $this->actingAs($user)
            ->delete(route('sessions.destroy', 'old-phone'))
            ->assertRedirect();

        expect(DB::table('sessions')->where('id', 'old-phone')->exists())->toBeFalse();
    });

    test("users can not end another user's session", function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        seedSession($other, 'their-phone');

        $this->actingAs($user)->delete(route('sessions.destroy', 'their-phone'));

        expect(DB::table('sessions')->where('id', 'their-phone')->exists())->toBeTrue();
    });

    test('ending all other sessions requires the password and rotates the remember token', function () {
        $user = User::factory()->create();
        seedSession($user, 'old-phone');
        seedSession($user, 'old-laptop');
        $rememberToken = $user->remember_token;

        $this->actingAs($user)
            ->delete(route('sessions.destroy-others'))
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('sessions.destroy-others'))
            ->assertRedirect();

        expect(DB::table('sessions')->where('user_id', $user->id)->whereIn('id', ['old-phone', 'old-laptop'])->count())->toBe(0)
            ->and($user->refresh()->remember_token)->not->toBe($rememberToken);
    });

    test('changing the password ends sessions on other devices', function () {
        $user = User::factory()->create();
        seedSession($user, 'old-phone');

        $this->actingAs($user)->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'nova-senha-2026',
            'password_confirmation' => 'nova-senha-2026',
        ])->assertSessionHasNoErrors();

        expect(DB::table('sessions')->where('id', 'old-phone')->exists())->toBeFalse();
    });
});
