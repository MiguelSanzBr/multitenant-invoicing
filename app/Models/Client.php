<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string $phone
 */
#[Fillable(['name', 'email', 'phone'])]
class Client extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ClientFactory> */
    use HasFactory;
}
