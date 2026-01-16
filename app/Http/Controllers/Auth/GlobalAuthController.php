<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class GlobalAuthController extends Controller
{

    public function getAllUsers()
    {
        $users = User::all();

        return response()->json([
            'status' => 'success',
            'data' => $users
        ], 200);
    }

    public function login(Request $request)
    {
        // 1. Validation des champs
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. Tentative de connexion
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Identifiants invalides'
            ], 401);
        }

        // 3. Récupération de l'utilisateur
        $user = User::where('email', $request->email)->firstOrFail();

        // 4. Création du token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        // 5. Retour des données essentielles pour React
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role, // Très important pour ton Dashboard
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnexion réussie']);
    }
}
