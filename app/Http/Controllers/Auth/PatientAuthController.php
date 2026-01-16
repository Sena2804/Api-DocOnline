<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\Medecin;
use App\Models\Clinique;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PatientAuthController extends Controller
{
    public function index(Request $request)
    {
        try {
            // 1. On récupère la collection
            $patients = Patient::orderBy('nom', 'asc')->get();

            // 2. On transforme directement la collection (pas de getCollection())
            $patients->transform(function ($patient) {
                $patient->photo_url = $patient->photo_profil
                    ? asset('storage/' . $patient->photo_profil)
                    : null;
                return $patient;
            });

            return response()->json($patients, 200);

        } catch (\Exception $e) {
            Log::error('Erreur récupération liste patients: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération des patients',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function register(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|string|email|unique:patients',
            'telephone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'password' => 'required|string|min:6',
            'photo_profil' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $data = [
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'telephone' => $request->telephone,
            'address' => $request->address,
            'password' => Hash::make($request->password),
        ];

        if ($request->hasFile('photo_profil')) {
            $data['photo_profil'] = $request->file('photo_profil')->store('photos/patients', 'public');
        }

        $patient = Patient::create($data);

        $token = $patient->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'patient' => $patient,
            'photo_url' => $patient->photo_profil ? asset('storage/' . $patient->photo_profil) : null,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $patient = Patient::where('email', $request->email)->first();

        if (! $patient || ! Hash::check($request->password, $patient->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email ou mot de passe incorrect.'],
            ]);
        }

        $token = $patient->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'patient' => $patient,
            'photo_url' => $patient->photo_profil ? asset('storage/' . $patient->photo_profil) : null,
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $patient = $request->user();

        $request->validate([
            'nom' => 'sometimes|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|unique:patients,email,' . $patient->id,
            'telephone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'photo_profil' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'groupe_sanguin' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-,inconnu',
            'serologie_vih' => 'nullable|in:positif,negatif,inconnu',
            'antecedents_medicaux' => 'nullable|string',
            'allergies' => 'nullable|string',
            'traitements_chroniques' => 'nullable|string',
        ]);

        // On prend tous les champs modifiables
        $data = $request->only([
            'nom',
            'prenom',
            'email',
            'telephone',
            'address',
            'groupe_sanguin',
            'serologie_vih',
            'antecedents_medicaux',
            'allergies',
            'traitements_chroniques',
        ]);

        // Gestion de la photo de profil
        if ($request->hasFile('photo_profil')) {
            if ($patient->photo_profil && Storage::disk('public')->exists($patient->photo_profil)) {
                Storage::disk('public')->delete($patient->photo_profil);
            }

            $data['photo_profil'] = $request->file('photo_profil')->store('photos/patients', 'public');
        }

        $patient->update($data);

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'patient' => $patient,
            'photo_url' => $patient->photo_profil ? asset('storage/' . $patient->photo_profil) : null,
        ]);
    }

    /**
     * Mettre à jour le mot de passe du patient
     */
    public function updatePassword(Request $request)
    {
        try {
            $patient = $request->user();

            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            // Vérifier que le mot de passe actuel est correct
            if (!Hash::check($validated['current_password'], $patient->password)) {
                return response()->json([
                    'error' => 'Mot de passe actuel incorrect',
                    'message' => 'Le mot de passe actuel que vous avez saisi est incorrect.'
                ], 422);
            }

            // Vérifier que le nouveau mot de passe est différent de l'ancien
            if (Hash::check($validated['new_password'], $patient->password)) {
                return response()->json([
                    'error' => 'Nouveau mot de passe identique',
                    'message' => 'Le nouveau mot de passe doit être différent de l\'ancien.'
                ], 422);
            }

            // Mettre à jour le mot de passe
            $patient->update([
                'password' => Hash::make($validated['new_password'])
            ]);

            // Supprimer tous les tokens existants (déconnexion de tous les appareils)
            $patient->tokens()->delete();

            // Créer un nouveau token
            $token = $patient->createToken('auth_token')->plainTextToken;

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
            Log::error('Erreur modification mot de passe patient: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la modification du mot de passe',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer le compte du patient
     */
    public function deleteAccount(Request $request)
    {
        try {
            $patient = $request->user();

            $validated = $request->validate([
                'password' => 'required|string',
            ]);

            // Vérifier le mot de passe avant suppression
            if (!Hash::check($validated['password'], $patient->password)) {
                return response()->json([
                    'error' => 'Mot de passe incorrect',
                    'message' => 'Le mot de passe saisi est incorrect. La suppression du compte a été annulée.'
                ], 422);
            }

            // Supprimer la photo de profil si elle existe
            if ($patient->photo_profil && Storage::disk('public')->exists($patient->photo_profil)) {
                Storage::disk('public')->delete($patient->photo_profil);
            }

            // Supprimer tous les tokens
            $patient->tokens()->delete();

            // Enregistrer l'email pour les logs (optionnel)
            $email = $patient->email;

            // Supprimer le patient
            $patient->delete();

            Log::info("Compte patient supprimé: {$email}");

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
            Log::error('Erreur suppression compte patient: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la suppression du compte',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Déconnexion du patient
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Déconnexion réussie'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur déconnexion patient: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la déconnexion',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour la photo de profil du patient
     */
    public function updatePhoto(Request $request)
    {
        try {
            $patient = $request->user();

            $validated = $request->validate([
                'photo_profil' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            // Supprimer l'ancienne photo
            if ($patient->photo_profil && file_exists(public_path('assets/images/' . $patient->photo_profil))) {
                unlink(public_path('assets/images/' . $patient->photo_profil));
            }

            // Sauvegarder dans public/assets/images/
            $file = $request->file('photo_profil');
            $fileName = 'photos/patients/' . time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('assets/images/photos/patients'), $fileName);

            $patient->update(['photo_profil' => $fileName]);

            return response()->json([
                'message' => 'Photo de profil mise à jour avec succès',
                'photo_profil' => $fileName,
                'photo_url' => asset('assets/images/' . $fileName), // URL CORRECTE
            ], 200);
        } catch (ValidationException $e) {
            Log::error('Erreur mise à jour photo patient: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur interne',
                'message' => 'Une erreur est survenue lors de la mise à jour de la photo.',
            ], 500);
        }
    }

    /**
     * Authentification Google avec Google_Client et certificats locaux
     */
    public function googleAuth(Request $request)
    {
        \Log::info('Google Auth Patient - Début avec Google_Client');

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

            // Utiliser le certificat local que vous avez téléchargé
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
                \Log::error('Google Auth - Token invalide');
                return response()->json(['error' => 'Token Google invalide'], 401);
            }

            \Log::info('Google Auth Patient - Payload vérifié', [
                'email' => $payload['email'],
                'google_id' => $payload['sub']
            ]);

            // Utiliser les données du payload
            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'] ?? 'Utilisateur Google';
            $givenName = $payload['given_name'] ?? '';
            $familyName = $payload['family_name'] ?? '';
            $emailVerified = $payload['email_verified'] ?? false;

            // Vérifier si l'email est vérifié chez Google
            if (!$emailVerified) {
                \Log::warning('Google Auth - Email non vérifié', ['email' => $email]);
                return response()->json(['error' => 'Email Google non vérifié'], 401);
            }

            // Séparer le nom et prénom
            $prenom = $givenName ?: explode(' ', $name)[0] ?? $name;
            $nom = $familyName ?: (explode(' ', $name)[1] ?? $name);

            // Chercher par google_id
            $patient = Patient::where('google_id', $googleId)->first();

            // Si pas trouvé, chercher par email
            if (!$patient) {
                $patient = Patient::where('email', $email)->first();
            }

            if (!$patient) {
                \Log::info('Google Auth Patient - Création nouveau patient', [
                    'email' => $email,
                    'google_id' => $googleId
                ]);

                $patient = Patient::create([
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'password' => Hash::make(\Illuminate\Support\Str::random(24)),
                    'google_id' => $googleId,
                    'telephone' => null,
                    'address' => null,
                    'email_verified_at' => now(), // Email vérifié par Google
                ]);
            } else {
                // Mettre à jour le google_id si l'utilisateur existe déjà
                if (!$patient->google_id) {
                    $patient->update([
                        'google_id' => $googleId,
                        'email_verified_at' => now() // Marquer comme vérifié si ce n'était pas le cas
                    ]);
                    \Log::info('Google Auth - Patient mis à jour avec Google ID', ['patient_id' => $patient->id]);
                }
            }

            // Créer le token d'accès
            $token = $patient->createToken('google-auth')->plainTextToken;

            \Log::info('Google Auth Patient - Succès', [
                'patient_id' => $patient->id,
                'email' => $patient->email
            ]);

            return response()->json([
                'patient' => $patient,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 200);
        } catch (\Firebase\JWT\BeforeValidException $e) {
            \Log::error('Google Auth - Token pas encore valide: ' . $e->getMessage());
            return response()->json([
                'error' => 'Token pas encore valide',
                'message' => 'Le token Google n\'est pas encore actif. Vérifiez l\'heure de votre appareil.'
            ], 401);
        } catch (\Firebase\JWT\ExpiredException $e) {
            \Log::error('Google Auth - Token expiré: ' . $e->getMessage());
            return response()->json([
                'error' => 'Token expiré',
                'message' => 'Le token Google a expiré'
            ], 401);
        } catch (\Google_Service_Exception $e) {
            \Log::error('Google Auth - Erreur Google API: ' . $e->getMessage(), [
                'code' => $e->getCode(),
                'details' => $e->getErrors() ?? []
            ]);
            return response()->json([
                'error' => 'Erreur de vérification Google',
                'message' => $e->getMessage()
            ], 500);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            \Log::error('Google Auth - Erreur réseau: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur de connexion réseau',
                'message' => 'Impossible de vérifier le token avec Google'
            ], 503);
        } catch (\Exception $e) {
            \Log::error('Google Auth Patient - Erreur: ' . $e->getMessage());

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

    /**
     * Récupérer le dossier médical complet du patient connecté
     */
    public function getDossierMedical(Request $request)
    {
        try {
            $patientId = auth()->id();

            $dossier = [
                'patient' => auth()->user(),
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
            Log::error('Erreur récupération dossier médical patient: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération du dossier médical',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les ordonnances du patient
     */
    public function getOrdonnances(Request $request)
    {
        try {
            $patientId = auth()->id();
            $ordonnances = \App\Models\Ordonnance::where('patient_id', $patientId)
                ->with(['medecin', 'medicaments'])
                ->orderBy('date_prescription', 'desc')
                ->get();

            return response()->json($ordonnances);

        } catch (\Exception $e) {
            Log::error('Erreur récupération ordonnances patient: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des ordonnances',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les arrêts maladie du patient
     */
    public function getArretsMaladie(Request $request)
    {
        try {
            $patientId = auth()->id();
            $arretsMaladie = \App\Models\ArretMaladie::where('patient_id', $patientId)
                ->with(['medecin'])
                ->orderBy('date_debut', 'desc')
                ->get();

            return response()->json($arretsMaladie);

        } catch (\Exception $e) {
            Log::error('Erreur récupération arrêts maladie patient: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des arrêts maladie',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer le dossier médical complet (alias pour compatibilité)
     */
    public function getDossierMedicalComplet(Request $request)
    {
        return $this->getDossierMedical($request);
    }
}
