<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Authentification par jeton Sanctum (contrat API, §« Authentification »).
 * `register` crée un foyer : un `user` sans organisation.
 */
class AuthController extends Controller
{
    /**
     * Hash bcrypt factice (jamais atteint par un mot de passe réel) : sert à
     * exécuter `Hash::check()` même quand le téléphone ne correspond à aucun
     * compte, pour que la durée de la réponse ne révèle pas l'existence d'un
     * compte (audit sécurité, [INFO] durcir le login — oracle temporel).
     */
    private const MOT_DE_PASSE_FACTICE = '$2y$12$O0zZNzHFlwWC9wOHCl4DS.c2BYSezqoHNX7WiUuZ7qvx5SAN8IykK';

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['nom'],
            'telephone' => $validated['telephone'],
            'password' => $validated['mot_de_passe'],
            'langue' => $validated['langue'] ?? 'fr',
        ]);

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('telephone', $validated['telephone'])->first();

        // Toujours exécuter Hash::check (même hash factice si l'utilisateur
        // n'existe pas) : le temps de calcul ne doit pas dépendre de
        // l'existence du compte.
        $motDePasseValide = Hash::check($validated['mot_de_passe'], $user->password ?? self::MOT_DE_PASSE_FACTICE);

        if (! $user || ! $motDePasseValide) {
            throw ValidationException::withMessages([
                'telephone' => ['Identifiants invalides.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }
}
