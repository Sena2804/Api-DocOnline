<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Clinique;
use App\Models\Patient;
use App\Models\Medecin;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CliniqueAuthController extends Controller
{
    public function index(Request $request)
    {
        try {
            // .get() permet de récupérer TOUTE la liste sans pagination
            $cliniques = Clinique::orderBy('nom', 'asc')->get();

            // On transforme la collection pour inclure l'URL complète de la photo
            $cliniques->transform(function ($clinique) {
                // Si la photo commence par "http", c'est une image Google OAuth
                if ($clinique->photo_profil && str_starts_with($clinique->photo_profil, 'http')) {
                    $clinique->photo_url = $clinique->photo_profil;
                } else {
                    $clinique->photo_url = $clinique->photo_profil
                        ? asset('storage/' . $clinique->photo_profil)
                        : null;
                }
                return $clinique;
            });

            return response()->json($cliniques, 200);

        } catch (\Exception $e) {
            Log::error('Erreur récupération liste cliniques: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération des cliniques',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function register(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:255',
            'email' => 'required|string|email|unique:cliniques',
            'telephone' => 'nullable|string|max:20',
            'address' => 'required|string|max:255',
            'description' => 'nullable|string',
            'password' => 'required|string|min:6',
            'photo_profil' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'type_etablissement' => 'nullable|string|max:255',
            'urgences_24h' => 'nullable|boolean',
            'parking_disponible' => 'nullable|boolean',
            'site_web' => 'nullable|url',
        ]);

        $data = [
            'nom' => $request->nom,
            'email' => $request->email,
            'telephone' => $request->telephone,
            'address' => $request->address,
            'description' => $request->description,
            'password' => Hash::make($request->password),
            'type_etablissement' => $request->type_etablissement,
            'urgences_24h' => $request->urgences_24h ?? false,
            'parking_disponible' => $request->parking_disponible ?? false,
            'site_web' => $request->site_web,
        ];

        if ($request->hasFile('photo_profil')) {
            $data['photo_profil'] = $request->file('photo_profil')->store('photos/cliniques', 'public');
        }

        $clinique = Clinique::create($data);

        $token = $clinique->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'clinique' => $clinique,
            'photo_url' => $clinique->photo_profil ? asset('storage/' . $clinique->photo_profil) : null,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $clinique = Clinique::where('email', $request->email)->first();

        if (! $clinique || ! Hash::check($request->password, $clinique->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email ou mot de passe incorrect.'],
            ]);
        }

        $token = $clinique->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'clinique' => $clinique,
            'photo_url' => $clinique->photo_profil ? asset('storage/' . $clinique->photo_profil) : null,
        ]);
    }

    public function profile(Request $request)
    {
        $clinique = $request->user();
        $clinique->load('medecins');
        return response()->json($clinique);
    }

    public function updateProfile(Request $request)
    {
        $cliniqueId = $request->id;
        $clinique = Clinique::findOrFail($cliniqueId);

        $request->validate([
            'nom' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|unique:cliniques,email,' . $clinique->id,
            'telephone' => 'nullable|string|max:20',
            'address' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'photo_profil' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'services' => 'nullable|array',
            'equipements' => 'nullable|array',
            'horaires' => 'nullable|array',
            'type_etablissement' => 'nullable|string|max:255',
            'urgences_24h' => 'nullable|boolean',
            'parking_disponible' => 'nullable|boolean',
            'site_web' => 'nullable|url',
        ]);

        $data = $request->only([
            'nom',
            'email',
            'telephone',
            'address',
            'description',
            'services',
            'equipements',
            'horaires',
            'type_etablissement',
            'urgences_24h',
            'parking_disponible',
            'site_web'
        ]);

        if ($request->hasFile('photo_profil')) {
            if ($clinique->photo_profil && Storage::disk('public')->exists($clinique->photo_profil)) {
                Storage::disk('public')->delete($clinique->photo_profil);
            }
            $data['photo_profil'] = $request->file('photo_profil')->store('photos/cliniques', 'public');
        }

        $clinique->update($data);

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'clinique' => $clinique,
            'photo_url' => $clinique->photo_profil ? asset('storage/' . $clinique->photo_profil) : null,
        ]);
    }

    /**
     * Mettre à jour le mot de passe de la clinique
     */
    public function updatePassword(Request $request)
    {
        try {
            $clinique = $request->user();

            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            // Vérifier que le mot de passe actuel est correct
            if (!Hash::check($validated['current_password'], $clinique->password)) {
                return response()->json([
                    'error' => 'Mot de passe actuel incorrect',
                    'message' => 'Le mot de passe actuel que vous avez saisi est incorrect.'
                ], 422);
            }

            // Vérifier que le nouveau mot de passe est différent de l'ancien
            if (Hash::check($validated['new_password'], $clinique->password)) {
                return response()->json([
                    'error' => 'Nouveau mot de passe identique',
                    'message' => 'Le nouveau mot de passe doit être différent de l\'ancien.'
                ], 422);
            }

            // Mettre à jour le mot de passe
            $clinique->update([
                'password' => Hash::make($validated['new_password'])
            ]);

            // Supprimer tous les tokens existants (déconnexion de tous les appareils)
            $clinique->tokens()->delete();

            // Créer un nouveau token
            $token = $clinique->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Mot de passe modifié avec succès',
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Erreur de validation',
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur modification mot de passe clinique: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la modification du mot de passe',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer le compte de la clinique
     */
    public function deleteAccount(Request $request)
    {
        try {
            $clinique = $request->user();

            $validated = $request->validate([
                'password' => 'required|string',
            ]);

            // Vérifier le mot de passe avant suppression
            if (!Hash::check($validated['password'], $clinique->password)) {
                return response()->json([
                    'error' => 'Mot de passe incorrect',
                    'message' => 'Le mot de passe saisi est incorrect. La suppression du compte a été annulée.'
                ], 422);
            }

            // Supprimer la photo de profil si elle existe
            if ($clinique->photo_profil && Storage::disk('public')->exists($clinique->photo_profil)) {
                Storage::disk('public')->delete($clinique->photo_profil);
            }

            // Détacher tous les médecins de la clinique
            $clinique->medecins()->detach();

            // Supprimer tous les tokens
            $clinique->tokens()->delete();

            // Enregistrer l'email pour les logs
            $email = $clinique->email;

            // Supprimer la clinique
            $clinique->delete();

            Log::info("Compte clinique supprimé: {$email}");

            return response()->json([
                'message' => 'Compte supprimé avec succès'
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Erreur de validation',
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur suppression compte clinique: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la suppression du compte',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Déconnexion de la clinique
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Déconnexion réussie'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur déconnexion clinique: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la déconnexion',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Ajouter un médecin à la clinique
    public function addMedecin(Request $request)
    {
        $clinique = $request->user();

        $request->validate([
            'medecin_id' => 'required|exists:medecins,id',
            'fonction' => 'nullable|string|max:255',
        ]);

        // Vérifier si le médecin n'est pas déjà dans la clinique
        if ($clinique->medecins()->where('medecin_id', $request->medecin_id)->exists()) {
            return response()->json([
                'message' => 'Ce médecin est déjà attaché à votre clinique'
            ], 422);
        }

        $clinique->medecins()->attach($request->medecin_id, [
            'fonction' => $request->fonction
        ]);

        $clinique->updateMedecinsCount();

        return response()->json([
            'message' => 'Médecin ajouté avec succès',
            'clinique' => $clinique->load('medecins')
        ]);
    }

    // Retirer un médecin de la clinique
    public function removeMedecin(Request $request, $medecinId)
    {
        $clinique = $request->user();

        $clinique->medecins()->detach($medecinId);
        $clinique->updateMedecinsCount();

        return response()->json([
            'message' => 'Médecin retiré avec succès',
            'clinique' => $clinique->load('medecins')
        ]);
    }

    // Lister les médecins de la clinique
    public function getMedecins(Request $request)
    {
        $clinique = $request->user();
        $medecins = $clinique->medecins()->with('user')->get();

        return response()->json($medecins);
    }

        /**
     * Mettre à jour la photo de profil de la clinique
     */
    public function updatePhoto(Request $request)
    {
        try {
            $clinique = $request->user();

            $validated = $request->validate([
                'photo_profil' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            // Supprimer l'ancienne photo si elle existe
            if ($clinique->photo_profil && Storage::disk('public')->exists($clinique->photo_profil)) {
                Storage::disk('public')->delete($clinique->photo_profil);
            }

            // Sauvegarder la nouvelle photo
            $data['photo_profil'] = $request->file('photo_profil')->store('photos/cliniques', 'public');

            $clinique->update($data);

            return response()->json([
                'message' => 'Photo de profil mise à jour avec succès',
                'photo_profil' => $clinique->photo_profil,
                'photo_url' => asset('storage/' . $clinique->photo_profil),
            ], 200);
        } catch (ValidationException $e) {
            Log::error('Erreur mise à jour photo clinique: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur de validation',
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour photo clinique: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur interne',
                'message' => 'Une erreur est survenue lors de la mise à jour de la photo.',
            ], 500);
        }
    }

    /**
     * Authentification Google pour les cliniques avec Google_Client et certificats locaux
     */
    public function googleAuth(Request $request)
    {
        \Log::info('Google Auth Clinique - Début avec Google_Client');

        try {
            $request->validate([
                'token' => 'required|string',
                'userType' => 'required|string|in:patient,medecin,clinique'
            ]);

            $token = $request->token;

            // Initialiser Google_Client
            $client = new \Google_Client([
                'client_id' => env('GOOGLE_CLIENT_ID'),
                'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            ]);

            // Utiliser le certificat local
            $certPath = storage_path('certs/cacert.pem');

            if (!file_exists($certPath)) {
                \Log::error('Certificat CA introuvable: ' . $certPath);
                return response()->json(['error' => 'Configuration SSL manquante'], 500);
            }

            $client->setHttpClient(new \GuzzleHttp\Client([
                'verify' => $certPath,
                'timeout' => 30,
                'connect_timeout' => 10,
            ]));

            // Vérifier le token avec Google
            $payload = $client->verifyIdToken($token);

            if (!$payload) {
                \Log::error('Google Auth Clinique - Token invalide');
                return response()->json(['error' => 'Token Google invalide'], 401);
            }

            \Log::info('Google Auth Clinique - Payload vérifié', [
                'email' => $payload['email'],
                'google_id' => $payload['sub']
            ]);

            // Utiliser les données du payload
            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'] ?? 'Clinique Google';
            $picture = $payload['picture'] ?? null;
            $emailVerified = $payload['email_verified'] ?? false;

            // Vérifier si l'email est vérifié chez Google
            if (!$emailVerified) {
                \Log::warning('Google Auth Clinique - Email non vérifié', ['email' => $email]);
                return response()->json(['error' => 'Email Google non vérifié'], 401);
            }

            // Chercher par google_id
            $clinique = Clinique::where('google_id', $googleId)->first();

            // Si pas trouvé, chercher par email
            if (!$clinique) {
                $clinique = Clinique::where('email', $email)->first();
            }

            if (!$clinique) {
                \Log::info('Google Auth Clinique - Création nouvelle clinique', [
                    'email' => $email,
                    'google_id' => $googleId
                ]);

                // Créer une nouvelle clinique avec des valeurs par défaut
                $clinique = Clinique::create([
                    'nom' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(24)),
                    'address' => 'Adresse à compléter',
                    'type_etablissement' => 'Clinique privée',
                    'google_id' => $googleId,
                    'photo_profil' => $picture,
                    'description' => 'Description à compléter',
                    'telephone' => null,
                    'site_web' => null,
                    'urgences_24h' => false,
                    'parking_disponible' => false,
                    'email_verified_at' => now(),
                ]);
            } else {
                \Log::info('Google Auth Clinique - Clinique existante trouvée', ['id' => $clinique->id]);

                // Mettre à jour le google_id si la clinique existe déjà
                if (!$clinique->google_id) {
                    $clinique->update([
                        'google_id' => $googleId,
                        'photo_profil' => $picture ?: $clinique->photo_profil,
                        'email_verified_at' => now()
                    ]);
                }
            }

            // Créer le token
            $token = $clinique->createToken('google-auth')->plainTextToken;

            \Log::info('Google Auth Clinique - Succès', [
                'clinique_id' => $clinique->id,
                'email' => $clinique->email
            ]);

            return response()->json([
                'clinique' => $clinique,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'photo_url' => $clinique->photo_profil ?: $picture,
            ], 200);
        } catch (\Firebase\JWT\BeforeValidException $e) {
            \Log::error('Google Auth Clinique - Token pas encore valide: ' . $e->getMessage());
            return response()->json([
                'error' => 'Token pas encore valide',
                'message' => 'Le token Google n\'est pas encore actif. Vérifiez l\'heure de votre appareil.'
            ], 401);
        } catch (\Firebase\JWT\ExpiredException $e) {
            \Log::error('Google Auth Clinique - Token expiré: ' . $e->getMessage());
            return response()->json([
                'error' => 'Token expiré',
                'message' => 'Le token Google a expiré'
            ], 401);
        } catch (\Google_Service_Exception $e) {
            \Log::error('Google Auth Clinique - Erreur Google API: ' . $e->getMessage(), [
                'code' => $e->getCode(),
                'details' => $e->getErrors() ?? []
            ]);
            return response()->json([
                'error' => 'Erreur de vérification Google',
                'message' => $e->getMessage()
            ], 500);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            \Log::error('Google Auth Clinique - Erreur réseau: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur de connexion réseau',
                'message' => 'Impossible de vérifier le token avec Google'
            ], 503);
        } catch (\Exception $e) {
            \Log::error('Google Auth Clinique - Erreur: ' . $e->getMessage());

            // Gestion spécifique de l'erreur "nbf" (not before)
            if (strpos($e->getMessage(), 'nbf') !== false || strpos($e->getMessage(), 'not before') !== false) {
                return response()->json([
                    'error' => 'Token pas encore valide',
                    'message' => 'Le token Google n\'est pas encore actif. Cela peut être dû à un décalage horaire.'
                ], 401);
            }

            return response()->json([
                'error' => 'Erreur d\'authentification Google',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $clinique = Clinique::find($id);

            if (!$clinique) {
                return response()->json([
                    'message' => 'clinique non trouvée'
                ], 404);
            }

            if ($clinique->photo_profil) {
                Storage::disk('public')->delete($clinique->photo_profil);
            }

            $clinique->delete();

            return response()->json([
                'message' => 'La clinique a été supprimée avec succès'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
