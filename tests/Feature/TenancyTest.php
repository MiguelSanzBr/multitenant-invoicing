<?php

declare(strict_types=1);

use App\Domain\Billing\InvoiceRepository;
use App\Domain\Tenancy\TenantContext;
use App\Infrastructure\Persistence\EloquentClientRepository;
use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Company;
use App\Infrastructure\Persistence\Models\Concerns\BelongsToCompany;
use App\Infrastructure\Persistence\Models\Invoice;
use App\Infrastructure\Persistence\Models\Reminder;
use App\Infrastructure\Persistence\Models\Scopes\CompanyScope;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('só enxerga dados da empresa do usuário logado', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $mine = Client::factory()->for($a->company)->create();
    Client::factory()->for($b->company)->create();

    $this->actingAs($a);

    expect(Client::pluck('id')->all())->toBe([$mine->id]);
});

it('sem usuário logado a consulta não devolve nada', function () {
    Client::factory()->create();

    expect(Client::count())->toBe(0)
        ->and(Client::withoutGlobalScopes()->count())->toBe(1);
});

it('preenche company_id com a empresa do usuário logado e ignora outra', function () {
    $user = User::factory()->create();
    $other = Company::factory()->create();

    $this->actingAs($user);
    $client = (new Client)->forceFill(['name' => 'Ana', 'phone' => '5511999990000', 'company_id' => $other->id]);
    $client->save();

    expect($client->company_id)->toBe($user->company_id)
        ->and($client->company->is($user->company))->toBeTrue();
});

it('todo model com company_id usa o escopo de tenant', function () {
    $tenantModels = collect(glob(app_path('Infrastructure/Persistence/Models/*.php')))
        ->map(fn (string $file) => 'App\\Infrastructure\\Persistence\\Models\\'.Str::before(basename($file), '.php'))
        ->filter(fn (string $class) => is_subclass_of($class, Model::class))
        ->filter(fn (string $class) => Schema::hasColumn((new $class)->getTable(), 'company_id'))
        // User é a exceção consciente: o login busca usuário antes de existir tenant
        ->reject(fn (string $class) => $class === User::class);

    expect($tenantModels)->toContain(Client::class);

    $tenantModels->each(fn (string $class) => expect(
        in_array(BelongsToCompany::class, class_uses_recursive($class)) && $class::hasGlobalScope(CompanyScope::class)
    )->toBeTrue("$class tem company_id mas não usa BelongsToCompany"));
});

it('dono e membro pertencem a uma empresa', function () {
    $owner = User::factory()->owner()->create();

    expect($owner->isOwner())->toBeTrue()
        ->and(User::factory()->create()->isOwner())->toBeFalse()
        ->and($owner->company->users->pluck('id')->all())->toBe([$owner->id]);
});

it('caso de uso sem usuário logado não descobre empresa', function () {
    app(TenantContext::class)->companyId();
})->throws(AuthenticationException::class);

it('regra de cliente com fatura vale mesmo sem usuário logado, sem depender do escopo', function () {
    $client = Client::factory()->create();
    Invoice::factory()->for($client)->create();
    $lonely = Client::factory()->for($client->company)->create();
    $repository = app(InvoiceRepository::class);
    $domain = fn (Client $c) => app(EloquentClientRepository::class)->findAcrossTenants($c->id);

    expect($repository->existsForClient($domain($client)))->toBeTrue()
        ->and($repository->existsForClient($domain($lonely)))->toBeFalse();
});

it('model de tenant não aceita mass assignment, escrita passa pelo repositório', function (string $model, array $attributes) {
    $this->actingAs(User::factory()->create());

    expect(fn () => $model::create($attributes))->toThrow(MassAssignmentException::class);
})->with([
    'cliente' => [Client::class, ['name' => 'Ana', 'phone' => '5511999990000']],
    'fatura paga pelo payload' => [Invoice::class, ['status' => 'paid', 'paid_at' => '2026-10-20']],
    'lembrete' => [Reminder::class, ['outcome' => 'sent']],
]);
