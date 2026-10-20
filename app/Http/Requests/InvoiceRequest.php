<?php

namespace App\Http\Requests;

use App\Enums\InvoiceStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            // o exists precisa do filtro por empresa, senão dá pra faturar cliente de outro tenant pelo id
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('company_id', $user->company_id)],
            'description' => ['required', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::in([InvoiceStatus::Draft->value, InvoiceStatus::Open->value])],
        ];
    }
}
