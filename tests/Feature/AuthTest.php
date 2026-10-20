<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

it('mostra a tela de login', function () {
    $this->get(route('login'))->assertOk();
});

it('autentica com a senha certa', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('não autentica com senha errada', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'errada']);

    $this->assertGuest();
});

it('limita tentativas de login', function () {
    $user = User::factory()->create();
    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'errada'])
        ->assertTooManyRequests();
});

it('faz logout', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

it('manda visitante pro login', function () {
    $this->get('/')->assertRedirect('/dashboard');
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('só existem as rotas de login e logout, sem as de confirmação de senha do Fortify', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/user/confirm-password')->assertNotFound();
    $this->post('/user/confirm-password', ['password' => 'password'])->assertNotFound();
    $this->get('/user/confirmed-password-status')->assertNotFound();
});

it('login mostra os usuários de demo só em ambiente local ou de demo', function (string $env, bool $shows) {
    app()->detectEnvironment(fn () => $env);

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $shows
        ? $page->where('demoUsers.0', ['email' => 'dona@acme.test', 'company' => 'Acme', 'role' => 'dona'])
            ->has('demoUsers', 3)
            ->where('demoPassword', 'password')
        : $page->missing('demoUsers')->missing('demoPassword'));
})->with([
    ['local', true],
    ['demo', true],
    ['testing', false],
    ['production', false],
]);
