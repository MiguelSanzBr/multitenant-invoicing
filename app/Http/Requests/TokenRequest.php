<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TokenRequest extends FormRequest
{
    public const ABILITIES = ['clients:read', 'clients:write', 'invoices:read', 'invoices:write'];

    // token vazado não pode disparar SMS: sem pedir escrita, sai só leitura
    public const DEFAULT_ABILITIES = ['clients:read', 'invoices:read'];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string', Rule::in(self::ABILITIES)],
        ];
    }

    public function email(): string
    {
        // igual ao login web (Fortify com lowercase_usernames)
        return mb_strtolower($this->string('email')->value());
    }

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        /** @var list<string> $abilities */
        $abilities = $this->input('abilities', self::DEFAULT_ABILITIES);

        return array_values(array_unique($abilities));
    }
}
