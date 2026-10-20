<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

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
