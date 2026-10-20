<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Billing\PhoneNumber;
use App\Domain\InvalidValue;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ClientRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            // a regra mora no PhoneNumber, aqui só traduz pra erro de formulário
            'phone' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    new PhoneNumber(is_string($value) ? $value : '');
                } catch (InvalidValue) {
                    $fail('O telefone precisa ter de 10 a 15 dígitos, com ou sem + na frente.');
                }
            }],
        ];
    }

    /**
     * @return array{string, string|null, string}
     */
    public function fields(): array
    {
        return [
            $this->string('name')->value(),
            $this->filled('email') ? $this->string('email')->value() : null,
            $this->string('phone')->value(),
        ];
    }
}
