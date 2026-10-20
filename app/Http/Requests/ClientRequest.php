<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'regex:/^\+?\d{10,15}$/'],
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
