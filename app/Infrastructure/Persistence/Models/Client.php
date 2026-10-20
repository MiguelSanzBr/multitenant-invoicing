<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Concerns\BelongsToCompany;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string $phone
 *
 * Sem fillable de propósito: toda escrita passa pelo repositório com forceFill a partir do domínio.
 */
#[UseFactory(ClientFactory::class)]
class Client extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ClientFactory> */
    use HasFactory;
}
