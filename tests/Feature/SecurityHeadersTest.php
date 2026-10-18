<?php

test('HTML responses carry the security headers', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]+'/");
});

test('the inline script uses the same nonce as the policy', function () {
    $response = $this->get(route('login'));

    preg_match("/'nonce-([A-Za-z0-9]+)'/", (string) $response->headers->get('Content-Security-Policy'), $matches);

    expect($matches[1] ?? null)->not->toBeNull()
        ->and($response->getContent())->toContain('<script nonce="'.$matches[1].'"');
});

test('HSTS is sent over HTTPS', function () {
    $this->get('https://localhost/entrar')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('JSON responses carry the headers too', function () {
    $this->getJson('/app-api/notifications/unread-count')
        ->assertUnauthorized()
        ->assertHeader('X-Frame-Options', 'DENY');
});
