<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models\Scopes;

use App\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * @implements Scope<Model>
 */
class CompanyScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            // sem usuário não existe tenant, então devolve vazio em vez de tudo.
            // command e job q precisam ver todas as empresas pedem withoutGlobalScope de forma explícita
            $builder->whereRaw('false');

            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $user->company_id);
    }
}
