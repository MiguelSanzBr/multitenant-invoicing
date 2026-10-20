<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // gateway de mensagens do outbox-gateway-demo, sem ele os lembretes falham e voltam pra fila
    'outbox' => [
        'url' => env('OUTBOX_URL', 'http://outbox-gateway:8080'),
        'timeout' => (int) env('OUTBOX_TIMEOUT', 10),
        // gateway fora do ar não pode segurar o worker pelo timeout inteiro
        'connect_timeout' => (int) env('OUTBOX_CONNECT_TIMEOUT', 2),
        // namespace da chave no gateway: staging e produção no mesmo outbox não podem colidir na fatura 42
        // teto anti-abuso do SMS: lembretes entregues por fatura e por empresa no dia
        'max_per_invoice' => (int) env('REMINDERS_MAX_PER_INVOICE', 3),
        'max_per_company_per_day' => (int) env('REMINDERS_MAX_PER_COMPANY_PER_DAY', 200),
        'key_prefix' => env('OUTBOX_KEY_PREFIX', Str::slug((string) env('APP_NAME', 'app')).'-'.env('APP_ENV', 'production').'-'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
