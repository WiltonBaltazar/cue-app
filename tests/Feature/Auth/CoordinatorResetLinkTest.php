<?php

use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

/*
| FR-006: volunteers without e-mail get a single-use, 24 h reset link from a
| coordinator, handed over in person.
*/

function issueLink(User $coordinator, Volunteer $volunteer): string
{
    test()->actingAs($coordinator)
        ->post(route('coord.reset-links.store', $volunteer))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('resetLink');

    $link = (string) session('resetLink');
    auth()->logout();

    return $link;
}

beforeEach(function () {
    $this->coordinator = User::factory()->coordinator()->create();
    $this->volunteer = Volunteer::factory()->active()->create();
});

test('a coordinator issues a link that sets a new password once', function () {
    $link = issueLink($this->coordinator, $this->volunteer);

    $this->get($link)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/set-password'));

    $this->post($link, ['password' => 'nova-senha-2026', 'password_confirmation' => 'nova-senha-2026'])
        ->assertRedirect(route('login'));

    expect(Hash::check('nova-senha-2026', $this->volunteer->user->refresh()->password))->toBeTrue();

    $this->get($link)->assertNotFound();

    expect(Activity::query()->where('event', 'issued_reset_link')->where('subject_id', $this->volunteer->id)->exists())->toBeTrue();
});

test('the link expires after 24 hours', function () {
    $link = issueLink($this->coordinator, $this->volunteer);

    $this->travel(25)->hours();

    $this->get($link)->assertNotFound();
});

test('issuing a new link invalidates the previous one', function () {
    $first = issueLink($this->coordinator, $this->volunteer);
    $second = issueLink($this->coordinator, $this->volunteer);

    $this->get($first)->assertNotFound();
    $this->get($second)->assertOk();
});

test('volunteers can not issue reset links', function () {
    $other = Volunteer::factory()->active()->create();

    $this->actingAs($other->user)
        ->post(route('coord.reset-links.store', $this->volunteer))
        ->assertForbidden();
});
