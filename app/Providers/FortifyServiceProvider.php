<?php

declare(strict_types=1);

namespace App\Providers;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    // só login e logout (routes/web.php): o resto do Fortify registraria confirmação de senha sem tela
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'status' => $request->session()->get('status'),
            // quem chega pelo portfólio entra sem caçar a senha no README; em produção não aparece
            ...app()->environment('local', 'demo')
                ? ['demoUsers' => DatabaseSeeder::DEMO_USERS, 'demoPassword' => DatabaseSeeder::DEMO_PASSWORD]
                : [],
        ]));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->string(Fortify::username())->value()).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
