<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Medecin;
use App\Models\Patient;
use App\Models\Clinique;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class MedecinAuthController extends Controller
{
    /**
     * Inscription d'un nouveau médecin
     */
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'email' => 'required|string|email|unique:medecins,email',
                'password' => 'required|string|min:6',
                'telephone' => 'nullable|string|max:20',
                'specialite' => 'required|string|max:255',
                'address' => 'nullable|string|max:500',
                'bio' => 'nullable|string',
                'photo_profil' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'type' => 'required|string|in:independant,clinique',
                'clinique_id' => 'required_if:type,clinique|nullable|exists:cliniques,id',
                'fonction' => 'nullable|string|max:255',
            ]);

            // Préparer les données du médecin
            $medecinData = [
                'nom' => $validated['nom'],
                'prenom' => $validated['prenom'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'telephone' => $validated['telephone'] ?? null,
                'specialite' => $validated['specialite'],
                'address' => $validated['address'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'type' => $validated['type'],
                'clinique_id' => $validated['type'] === 'clinique' ? $validated['clinique_id'] : null,
            ];

            // Gérer l'upload de la photo
            if ($request->hasFile('photo_profil')) {
                $medecinData['photo_profil'] = $request->file('photo_profil')->store('photos/medecins', 'public');
            }

            // Créer le médecin
            $medecin = Medecin::create($medecinData);

            // Si rattaché à une clinique, créer la relation many-to-many
            if ($validated['type'] === 'clinique' && isset($validated['clinique_id'])) {
                $clinique = Clinique::findOrFail($validated['clinique_id']);

                if (method_exists($clinique, 'medecins')) {
                    $clinique->medecins()->attach($medecin->id, [
                        'fonction' => $validated['fonction'] ?? 'Médecin',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Générer le token
            $token = $medecin->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Inscription réussie',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'medecin' => $medecin,
                'photo_url' => $medecin->photo_profil ? asset('storage/' . $medecin->photo_profil) : null,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Erreur de validation',
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur inscription médecin: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de l\'inscription',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Connexion d'un médecin
     */
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            $medecin = Medecin::where('email', $validated['email'])->first();

            if (!$medecin || !Hash::check($validated['password'], $medecin->password)) {
                throw ValidationException::withMessages([
                    'email' => ['Email ou mot de passe incorrect.'],
                ]);
            }

            // Supprimer les anciens tokens (optionnel)
            $medecin->tokens()->delete();

            // Créer un nouveau token
            $token = $medecin->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Connexion réussie',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'medecin' => $medecin,
                'photo_url' => $medecin->photo_profil ? asset('storage/' . $medecin->photo_profil) : null,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Erreur de validation',
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur connexion médecin: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la connexion',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer le profil du médecin connecté
     */
    public function profile(Request $request)
    {
        try {
            $medecin = $request->user();

            // Charger les relations
            $medecin->load(['clinique', 'cliniques']);

            // Décoder working_hours si c'est une chaîne JSON
            if ($medecin->working_hours && is_string($medecin->working_hours)) {
                $medecin->working_hours = json_decode($medecin->working_hours);
            }

            return response()->json($medecin, 200);
        } catch (\Exception $e) {
            Log::error('Erreur récupération profil médecin: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération du profil',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour le profil du médecin
     */
    public function updateProfile(Request $request)
    {
        try {
            $medecinId = $request->id;
            $medecin = Medecin::findOrFail($medecinId);

            $validated = $request->validate([
                'id' => 'required|exists:medecins,id',
                'nom' => 'sometimes|string|max:255',
                'prenom' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|unique:medecins,email,' . $medecin->id,
                'telephone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'specialite' => 'nullable|string|max:255',
                'experience_years' => 'nullable|integer|min:0|max:100',
                'languages' => 'nullable|string',
                'professional_background' => 'nullable|string',
                'consultation_price' => 'nullable|integer|min:0',
                'insurance_accepted' => 'nullable|boolean',
                'bio' => 'nullable|string',
                'working_hours' => 'nullable|array',
                'type' => 'sometimes|string|in:independant,clinique',
                'clinique_id' => 'required_if:type,clinique|nullable|exists:cliniques,id',
                'fonction' => 'nullable|string|max:255',
            ]);

            // Préparer les données à mettre à jour
            $dataToUpdate = collect($validated)->except(['type', 'clinique_id', 'fonction', 'working_hours'])->toArray();

            // Gérer les horaires de travail
            if (isset($validated['working_hours'])) {
                $dataToUpdate['working_hours'] = json_encode($validated['working_hours']);
            }

            // Gérer le type de pratique
            if (isset($validated['type'])) {
                $dataToUpdate['type'] = $validated['type'];

                if ($validated['type'] === 'clinique' && isset($validated['clinique_id'])) {
                    $dataToUpdate['clinique_id'] = $validated['clinique_id'];

                    // Ajouter à la relation many-to-many si nécessaire
                    $clinique = Clinique::findOrFail($validated['clinique_id']);
                    if (!$medecin->cliniques->contains($clinique->id)) {
                        $clinique->medecins()->attach($medecin->id, [
                            'fonction' => $validated['fonction'] ?? 'Médecin',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } else {
                    // Si indépendant, retirer les relations cliniques
                    $dataToUpdate['clinique_id'] = null;
                    $medecin->cliniques()->detach();
                }
            }

            // Mettre à jour le médecin
            $medecin->update($dataToUpdate);

            // Recharger le médecin avec les relations
            $medecin->refresh();
            $medecin->load(['clinique', 'cliniques']);

            // Décoder working_hours pour le retour
            if ($medecin->working_hours && is_string($medecin->working_hours)) {
                $dataToUpdate['working_hours'] = $validated['working_hours'];
            }

            return response()->json($medecin, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Erreur de validation',
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour profil médecin: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la mise à jour du profil',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour la photo de profil
     */
    public function updatePhoto(Request $request)
    {
        try {
            $medecin = $request->user();

            // Validation stricte + taille max 5 Mo
            $validated = $request->validate([
                'photo_profil' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120', // 5120 = 5 Mo
            ]);

            // Suppression de l'ancienne photo si elle existe
            if ($medecin->photo_profil && Storage::disk('public')->exists($medecin->photo_profil)) {
                Storage::disk('public')->delete($medecin->photo_profil);
            }

            // Sauvegarde de la nouvelle photo
            $photoPath = $request->file('photo_profil')->store('photos/medecins', 'public');
            $medecin->update(['photo_profil' => $photoPath]);

            return response()->json([
                'message' => 'Photo de profil mise à jour avec succès',
                'photo_profil' => $photoPath,
                'photo_url' => asset('storage/' . $photoPath),
            ], 200);
        } catch (ValidationException $e) {
            // Message utilisateur clair
            $errors = $e->errors();
            $firstError = collect($errors)->flatten()->first() ?? 'Erreur de validation du fichier.';

            return response()->json([
                'error' => 'Erreur de validation',
                'message' => $firstError,
            ], 422);
        } catch (\Exception $e) {
            // Log interne + message générique côté front
            Log::error('Erreur mise à jour photo médecin: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur interne',
                'message' => 'Une erreur est survenue lors de la mise à jour de la photo.',
            ], 500);
        }
    }


    /**
     * Mettre à jour les horaires de travail (route séparée si nécessaire)
     */
    public function updateWorkingHours(Request $request)
    {
        try {
            $medecin = $request->user();

            $validated = $request->validate([
                'working_hours' => 'required|array',
                'working_hours.*.day' => 'required|string',
                'working_hours.*.hours' => 'required|string',
            ]);

            // Convertir en JSON
            $workingHours = json_encode($validated['working_hours']);

            // Mettre à jour
            $medecin->update(['working_hours' => $workingHours]);

            return response()->json([
                'message' => 'Horaires mis à jour avec succès',
                'working_hours' => json_decode($workingHours),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Erreur de validation',
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour horaires médecin: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la mise à jour des horaires',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Déconnexion (optionnel)
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Déconnexion réussie'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur déconnexion médecin: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la déconnexion',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function updatePassword(Request $request)
    {
        try {
            $medecin = $request->user();

            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            // Vérifier que le mot de passe actuel est correct
            if (!Hash::check($validated['current_password'], $medecin->password)) {
                return response()->json([
                    'error' => 'Mot de passe actuel incorrect',
                    'message' => 'Le mot de passe actuel que vous avez saisi est incorrect.'
                ], 422);
            }

            // Vérifier que le nouveau mot de passe est différent de l'ancien
            if (Hash::check($validated['new_password'], $medecin->password)) {
                return response()->json([
                    'error' => 'Nouveau mot de passe identique',
                    'message' => 'Le nouveau mot de passe doit être différent de l\'ancien.'
                ], 422);
            }

            // Mettre à jour le mot de passe
            $medecin->update([
                'password' => Hash::make($validated['new_password'])
            ]);

            // Supprimer tous les tokens existants (déconnexion de tous les appareils)
            $medecin->tokens()->delete();

            // Créer un nouveau token
            $token = $medecin->createToken('auth_token')->plainTextToken;

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
            Log::error('Erreur modification mot de passe médecin: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la modification du mot de passe',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer le compte du médecin
     */
    public function deleteAccount(Request $request)
    {
        try {
            $medecin = $request->user();

            $validated = $request->validate([
                'password' => 'required|string',
            ]);

            // Vérifier le mot de passe avant suppression
            if (!Hash::check($validated['password'], $medecin->password)) {
                return response()->json([
                    'error' => 'Mot de passe incorrect',
                    'message' => 'Le mot de passe saisi est incorrect. La suppression du compte a été annulée.'
                ], 422);
            }

            // Supprimer la photo de profil si elle existe
            if ($medecin->photo_profil && Storage::disk('public')->exists($medecin->photo_profil)) {
                Storage::disk('public')->delete($medecin->photo_profil);
            }

            // Supprimer tous les tokens
            $medecin->tokens()->delete();

            // Enregistrer l'email pour les logs (optionnel)
            $email = $medecin->email;

            // Supprimer le médecin
            $medecin->delete();

            Log::info("Compte médecin supprimé: {$email}");

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
            Log::error('Erreur suppression compte médecin: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la suppression du compte',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Authentification Google pour les médecins avec Google_Client et certificats locaux
     */
    public function googleAuth(Request $request)
    {
        \Log::info('Google Auth Médecin - Début avec Google_Client');

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
                \Log::error('Google Auth Médecin - Token invalide');
                return response()->json(['error' => 'Token Google invalide'], 401);
            }

            \Log::info('Google Auth Médecin - Payload vérifié', [
                'email' => $payload['email'],
                'google_id' => $payload['sub']
            ]);

            // Utiliser les données du payload
            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'] ?? 'Docteur Google';
            $givenName = $payload['given_name'] ?? '';
            $familyName = $payload['family_name'] ?? '';
            $picture = $payload['picture'] ?? null;
            $emailVerified = $payload['email_verified'] ?? false;

            // Vérifier si l'email est vérifié chez Google
            if (!$emailVerified) {
                \Log::warning('Google Auth Médecin - Email non vérifié', ['email' => $email]);
                return response()->json(['error' => 'Email Google non vérifié'], 401);
            }

            // Séparer le nom et prénom
            $prenom = $givenName ?: explode(' ', $name)[0] ?? $name;
            $nom = $familyName ?: (explode(' ', $name)[1] ?? $name);

            // Chercher par google_id
            $medecin = Medecin::where('google_id', $googleId)->first();

            // Si pas trouvé, chercher par email
            if (!$medecin) {
                $medecin = Medecin::where('email', $email)->first();
            }

            if (!$medecin) {
                \Log::info('Google Auth Médecin - Création nouveau médecin', [
                    'email' => $email,
                    'google_id' => $googleId
                ]);

                // Créer un nouveau médecin avec des valeurs par défaut
                $medecin = Medecin::create([
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'password' => Hash::make(Str::random(24)),
                    'specialite' => 'Médecine Générale',
                    'type' => 'independant',
                    'google_id' => $googleId,
                    'photo_profil' => $picture,
                    'address' => 'Adresse à compléter',
                    'telephone' => null,
                    'bio' => null,
                    'email_verified_at' => now(),
                ]);
            } else {
                \Log::info('Google Auth Médecin - Médecin existant trouvé', ['id' => $medecin->id]);

                // Mettre à jour le google_id si le médecin existe déjà
                if (!$medecin->google_id) {
                    $medecin->update([
                        'google_id' => $googleId,
                        'photo_profil' => $picture ?: $medecin->photo_profil,
                        'email_verified_at' => now()
                    ]);
                }
            }

            // Créer le token
            $token = $medecin->createToken('google-auth')->plainTextToken;

            \Log::info('Google Auth Médecin - Succès', [
                'medecin_id' => $medecin->id,
                'email' => $medecin->email
            ]);

            return response()->json([
                'medecin' => $medecin,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'photo_url' => $medecin->photo_profil ?: $picture,
            ], 200);
        } catch (\Firebase\JWT\BeforeValidException $e) {
            \Log::error('Google Auth Médecin - Token pas encore valide: ' . $e->getMessage());
            return response()->json([
                'error' => 'Token pas encore valide',
                'message' => 'Le token Google n\'est pas encore actif. Vérifiez l\'heure de votre appareil.'
            ], 401);
        } catch (\Firebase\JWT\ExpiredException $e) {
            \Log::error('Google Auth Médecin - Token expiré: ' . $e->getMessage());
            return response()->json([
                'error' => 'Token expiré',
                'message' => 'Le token Google a expiré'
            ], 401);
        } catch (\Google_Service_Exception $e) {
            \Log::error('Google Auth Médecin - Erreur Google API: ' . $e->getMessage(), [
                'code' => $e->getCode(),
                'details' => $e->getErrors() ?? []
            ]);
            return response()->json([
                'error' => 'Erreur de vérification Google',
                'message' => $e->getMessage()
            ], 500);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            \Log::error('Google Auth Médecin - Erreur réseau: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur de connexion réseau',
                'message' => 'Impossible de vérifier le token avec Google'
            ], 503);
        } catch (\Exception $e) {
            \Log::error('Google Auth Médecin - Erreur: ' . $e->getMessage());

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

    public function createOrdonnance(Request $request)
    {
        try {
            $validated = $request->validate([
                'appointment_id' => 'required|exists:appointments,id',
                'patient_id' => 'required|exists:patients,id',
                'medecin_id' => 'required|exists:medecins,id',
                'date_validite' => 'required|date',
                'instructions' => 'nullable|string',
                'renouvellements' => 'integer|min:0',
                'medicaments' => 'required|array|min:1',
                'medicaments.*.nom' => 'required|string',
                'medicaments.*.dosage' => 'required|string',
                'medicaments.*.posologie' => 'required|string',
                'medicaments.*.duree' => 'required|string',
                'medicaments.*.quantite' => 'required|string',
                'medicaments.*.instructions' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $ordonnance = \App\Models\Ordonnance::create([
                'appointment_id' => $validated['appointment_id'],
                'patient_id' => $validated['patient_id'],
                'medecin_id' => $validated['medecin_id'],
                'date_prescription' => now(),
                'date_validite' => $validated['date_validite'],
                'instructions' => $validated['instructions'],
                'renouvellements' => $validated['renouvellements'] ?? 0,
                'statut' => 'active',
            ]);

            // Sauvegarder les médicaments
            foreach ($validated['medicaments'] as $medicamentData) {
                $ordonnance->medicaments()->create($medicamentData);
            }

            DB::commit();

            // Charger les relations pour la réponse
            $ordonnance->load(['medicaments', 'patient', 'medecin']);

            return response()->json([
                'message' => 'Ordonnance créée avec succès',
                'ordonnance' => $ordonnance
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création ordonnance: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la création de l\'ordonnance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un arrêt de maladie
     */
    public function createArretMaladie(Request $request)
    {
        try {
            $validated = $request->validate([
                'appointment_id' => 'required|exists:appointments,id',
                'patient_id' => 'required|exists:patients,id',
                'medecin_id' => 'required|exists:medecins,id',
                'date_debut' => 'required|date',
                'date_fin' => 'required|date|after:date_debut',
                'duree_jours' => 'required|integer|min:1',
                'motif' => 'required|string',
                'diagnostic' => 'required|string',
                'recommandations' => 'nullable|string',
                'renouvelable' => 'boolean',
                'date_visite_controle' => 'nullable|date|after:date_fin',
            ]);

            $arretMaladie = \App\Models\ArretMaladie::create([
                'appointment_id' => $validated['appointment_id'],
                'patient_id' => $validated['patient_id'],
                'medecin_id' => $validated['medecin_id'],
                'date_debut' => $validated['date_debut'],
                'date_fin' => $validated['date_fin'],
                'duree_jours' => $validated['duree_jours'],
                'motif' => $validated['motif'],
                'diagnostic' => $validated['diagnostic'],
                'recommandations' => $validated['recommandations'],
                'renouvelable' => $validated['renouvelable'] ?? false,
                'date_visite_controle' => $validated['date_visite_controle'],
                'statut' => 'actif',
            ]);

            $arretMaladie->load(['patient', 'medecin']);

            return response()->json([
                'message' => 'Arrêt de maladie créé avec succès',
                'arret_maladie' => $arretMaladie
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur création arrêt maladie: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la création de l\'arrêt de maladie',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Partager le dossier médical avec un autre médecin
     */
    public function shareMedicalRecord(Request $request)
    {
        try {
            $validated = $request->validate([
                'appointmentId' => 'required|exists:appointments,id',
                'patientId' => 'required|exists:patients,id',
                'targetDoctorId' => 'required|exists:medecins,id',
                'reason' => 'required|string',
                'sharedBy' => 'required|string',
            ]);

            $share = \App\Models\MedicalRecordShare::create([
                'appointment_id' => $validated['appointmentId'],
                'patient_id' => $validated['patientId'],
                'medecin_source_id' => auth()->id(),
                'medecin_dest_id' => $validated['targetDoctorId'],
                'reason' => $validated['reason'],
                'shared_at' => now(),
                'access_expires_at' => now()->addDays(30),
            ]);

            return response()->json([
                'message' => 'Dossier médical partagé avec succès',
                'share' => $share
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur partage dossier médical: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors du partage du dossier médical',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer le dossier médical complet d'un patient
     */
    public function getDossierMedicalPatient($patientId)
    {
        try {
            $patient = Patient::findOrFail($patientId);

            $dossier = [
                'patient' => $patient,
                'antecedents' => \App\Models\Antecedent::where('patient_id', $patientId)->get(),
                'consultations' => \App\Models\Appointment::where('patient_id', $patientId)
                    ->with(['medecin'])
                    ->orderBy('date', 'desc')
                    ->get(),
                'ordonnances' => \App\Models\Ordonnance::where('patient_id', $patientId)
                    ->with(['medecin', 'medicaments'])
                    ->orderBy('date_prescription', 'desc')
                    ->get(),
                'arrets_maladie' => \App\Models\ArretMaladie::where('patient_id', $patientId)
                    ->with(['medecin'])
                    ->orderBy('date_debut', 'desc')
                    ->get(),
                'examens' => \App\Models\Examen::where('patient_id', $patientId)
                    ->orderBy('date_prescription', 'desc')
                    ->get(),
            ];

            return response()->json($dossier);

        } catch (\Exception $e) {
            Log::error('Erreur récupération dossier médical: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération du dossier médical',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les ordonnances d'un médecin
     */
    public function getOrdonnances(Request $request)
    {
        try {
            $medecinId = auth()->id();
            $ordonnances = \App\Models\Ordonnance::where('medecin_id', $medecinId)
                ->with(['patient', 'medicaments'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($ordonnances);

        } catch (\Exception $e) {
            Log::error('Erreur récupération ordonnances: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des ordonnances',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les arrêts maladie d'un médecin
     */
    public function getArretsMaladie(Request $request)
    {
        try {
            $medecinId = auth()->id();
            $arretsMaladie = \App\Models\ArretMaladie::where('medecin_id', $medecinId)
                ->with(['patient'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($arretsMaladie);

        } catch (\Exception $e) {
            Log::error('Erreur récupération arrêts maladie: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des arrêts maladie',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer une ordonnance spécifique
     */
    public function getOrdonnance($id)
    {
        try {
            $ordonnance = \App\Models\Ordonnance::with(['patient', 'medecin', 'medicaments'])
                ->findOrFail($id);

            return response()->json($ordonnance);

        } catch (\Exception $e) {
            Log::error('Erreur récupération ordonnance: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération de l\'ordonnance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer un arrêt maladie spécifique
     */
    public function getArretMaladie($id)
    {
        try {
            $arretMaladie = \App\Models\ArretMaladie::with(['patient', 'medecin'])
                ->findOrFail($id);

            return response()->json($arretMaladie);

        } catch (\Exception $e) {
            Log::error('Erreur récupération arrêt maladie: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération de l\'arrêt maladie',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer tous les médecins pour le partage (excluant le médecin connecté)
     */
    public function getAllMedecinsForSharing()
    {
        try {
            $medecins = Medecin::where('id', '!=', auth()->id())
                ->select('id', 'prenom', 'nom', 'specialite', 'email', 'ville')
                ->get();

            return response()->json($medecins);

        } catch (\Exception $e) {
            Log::error('Erreur récupération médecins: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des médecins',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteMedecin($id)
    {
        try {
            $medecin = Medecin::findOrFail($id);

            // Supprimer les relations dans la table pivot avant de supprimer le médecin
            $medecin->cliniques()->detach();

            // Supprimer le compte (et éventuellement la photo si elle existe)
            $medecin->delete();

            return response()->json(['message' => 'Médecin supprimé avec succès'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la suppression'], 500);
        }
    }
}
