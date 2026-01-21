<?php

namespace App\Http\Controllers;

use App\Models\Clinique;
use Illuminate\Http\Request;

class CliniqueController extends Controller
{
    /**
     * Récupérer toutes les cliniques
     */
    public function index(Request $request)
    {
        try {
            // On commence par charger la relation medecins si nécessaire
            $query = Clinique::query()->with('medecins');

            // Recherche par nom, type ou adresse
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('nom', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('type_etablissement', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('address', 'LIKE', '%' . $searchTerm . '%');
                });
            }

            // On définit les colonnes à sélectionner
            $columns = [
                'id', 'nom', 'email', 'telephone', 'address',
                'type_etablissement', 'description', 'photo_profil',
                'urgences_24h', 'parking_disponible', 'site_web', 'created_at'
            ];

            // REMPLACEMENT de ->get() par ->paginate()
            $cliniques = $query->select($columns)
                            ->orderBy('nom', 'asc')
                            ->paginate(10);

            // Transformation pour ajouter l'URL complète de la photo
            $cliniques->through(function ($clinique) {
                $clinique->photo_url = $clinique->photo_profil
                    ? asset('storage/' . $clinique->photo_profil)
                    : null;
                return $clinique;
            });

            // Retourne l'objet de pagination complet (data, total, current_page, etc.)
            return response()->json($cliniques);

        } catch (\Exception $e) {
            \Log::error('Erreur index cliniques: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération des cliniques',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer une clinique spécifique AVEC SES MÉDECINS
     */
    public function show($id)
    {
        try {
            // Charger la clinique avec ses médecins associés (sans la colonne rating)
            $clinique = Clinique::with(['medecins' => function ($query) {
                $query->select([
                    'medecins.id',
                    'medecins.prenom',
                    'medecins.nom',
                    'medecins.specialite',
                    'medecins.photo_profil',
                    'medecins.experience_years',
                    'medecins.telephone',
                    'medecins.email'
                    // Retirer 'medecins.rating' qui n'existe pas
                ])->withPivot('fonction');
            }])->findOrFail($id);

            return response()->json($clinique);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Clinique non trouvée',
                'message' => $e->getMessage()
            ], 404);
        }
    }
}
