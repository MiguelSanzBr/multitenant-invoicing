<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\TokenRequest;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController
{
    // bcrypt custo 12 de um texto qualquer: e-mail inexistente paga o mesmo hash e o tempo não entrega quem é usuário
    private const DUMMY_HASH = '$2y$12$L8Cw40nJ/fd6RhUBLXP3T.WhxgtWnkHlDOKqs6h7uG8ew2fSBabCu';

    public function store(TokenRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email())->first();
        $valid = Hash::check($request->string('password')->value(), $user->password ?? self::DUMMY_HASH);

        if (! $user || ! $valid) {
            throw ValidationException::withMessages(['email' => 'Credenciais inválidas.']);
        }

        $token = $user->createToken(
            $request->string('device_name')->value(),
            $request->abilities(),
            now()->addMinutes(Config::integer('sanctum.expiration')),
        );

        return response()->json(['token' => $token->plainTextToken], 201);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $user->tokens()->oldest('id')->get()->map(fn (PersonalAccessToken $token) => [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
        ])]);
    }

    public function destroy(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()->delete();

        return response()->noContent();
    }
}
