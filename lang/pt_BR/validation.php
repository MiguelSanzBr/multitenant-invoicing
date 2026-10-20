<?php

declare(strict_types=1);

// só as regras q o app usa
return [
    'date_format' => 'O campo :attribute precisa estar no formato :format.',
    'email' => 'O campo :attribute precisa ser um e-mail válido.',
    'exists' => 'O :attribute selecionado é inválido.',
    'in' => 'O :attribute selecionado é inválido.',
    'integer' => 'O campo :attribute precisa ser um número inteiro.',
    'max' => ['numeric' => 'O campo :attribute não pode passar de :max.', 'string' => 'O campo :attribute não pode ter mais de :max caracteres.'],
    'min' => ['numeric' => 'O campo :attribute precisa ser no mínimo :min.'],
    'regex' => 'O campo :attribute está num formato inválido.',
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute precisa ser texto.',

    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'phone' => 'telefone',
        'password' => 'senha',
        'client_id' => 'cliente',
        'description' => 'descrição',
        'amount_cents' => 'valor',
        'due_date' => 'vencimento',
        'status' => 'status',
    ],
];
