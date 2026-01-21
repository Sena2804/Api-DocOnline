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

    public function registerAdmin(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string'
        ]);

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => bcrypt($fields['password']),
            'role' => $fields['role'],
        ]);

        return response($user, 201);
    }

    // Modifier un utilisateur
    public function updateUser(Request $request)
    {
        $userId = $request->id;
        $user = User::findOrFail($userId);

        // Utilisation de nullable pour ne modifier que ce qui est nécessaire
        $fields = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|unique:users,email,' . $userId,
        ]);

        // On filtre les champs null pour ne pas écraser les données par du vide
        $user->update(array_filter($fields));

        return response($user, 200);
    }

    // Supprimer un utilisateur
    public function destroyUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response(['message' => 'Utilisateur supprimé avec succès'], 200);
    }
}
