<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Billing\Client;
use App\Domain\Billing\ClientRepository;
use App\Domain\Billing\PhoneNumber;
use App\Infrastructure\Persistence\Models\Client as ClientModel;
use App\Infrastructure\Persistence\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;

final class EloquentClientRepository implements ClientRepository
{
    public function find(int $id): ?Client
    {
        $model = ClientModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findAcrossTenants(int $id): ?Client
    {
        $model = ClientModel::withoutGlobalScope(CompanyScope::class)->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Client $client): Client
    {
        $model = $client->id === null ? new ClientModel : $this->owned($client)->firstOrFail();
        $model->forceFill([
            'company_id' => $client->companyId,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone->value,
        ])->save();

        return $this->toDomain($model);
    }

    public function delete(Client $client): void
    {
        $this->owned($client)->delete();
    }

    /**
     * Busca pela empresa da própria entidade, assim funciona com ou sem usuário logado.
     *
     * @return Builder<ClientModel>
     */
    private function owned(Client $client): Builder
    {
        return ClientModel::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $client->companyId)
            ->whereKey($client->id);
    }

    private function toDomain(ClientModel $model): Client
    {
        return new Client($model->id, $model->company_id, $model->name, $model->email, new PhoneNumber($model->phone));
    }
}
