<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models\Concerns;

use App\Infrastructure\Persistence\Models\Company;
use App\Infrastructure\Persistence\Models\Scopes\CompanyScope;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $company_id
 * @property-read Company $company
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model): void {
            $user = Auth::user();

            // com usuário logado o company_id vem sempre dele, nunca do payload
            if ($user instanceof User) {
                $model->setAttribute('company_id', $user->company_id);
            }
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
