<?php

declare(strict_types=1);

it('manda os headers de segurança na web e na API', function (string $url) {
    $this->get($url)
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security');
})->with(['/login', '/api/v1/clients', '/up']);

it('HSTS só em produção', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
