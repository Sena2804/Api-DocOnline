<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Medecin;
use Illuminate\Support\Facades\Auth;

class MedecinController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // Retourne la liste de tous les médecins avec leurs relations
    public function index()
    {
        $medecins = Medecin::with(['clinique', 'cliniques'])->orderBy('nom', 'asc')->paginate(10);
        return response()->json($medecins);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email|unique:medecins,email',
            'password' => 'required|min:6',
            'photo_profil' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $data = $request->all();
        $data['password'] = bcrypt($data['password']);

        if ($request->hasFile('photo_profil')) {
            $data['photo_profil'] = $request->file('photo_profil')->store('assets/images/medecins', 'public');
        }

        $medecin = Medecin::create($data);

        return response()->json([
            'message' => 'Médecin créé avec succès',
            'data' => $medecin,
            'photo_url' => $medecin->photo_profil ? asset('assets/images' . $medecin->photo_profil) : null
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        // Charger le médecin avec ses relations
        $medecin = Medecin::with(['clinique', 'cliniques', 'reviews.patient'])->find($id);

        if (!$medecin) {
            return response()->json(['message' => 'Médecin non trouvé'], 404);
        }

        // Ajouter les statistiques des avis
        $medecin->average_rating = $medecin->averageRating();
        $medecin->reviews_count = $medecin->reviewsCount();

        return response()->json($medecin);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateProfile(Request $request, $id)
    {
        $medecin = Medecin::findOrFail($id);

        $request->validate([
            'nom' => 'sometimes|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:medecins,email,' . $medecin->id,
            'photo_profil' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $data = $request->all();

        if ($request->hasFile('photo_profil')) {
            $data['photo_profil'] = $request->file('photo_profil')->store('photos/medecins', 'public');
        }

        $medecin->update($data);

        // Recharger les relations après la mise à jour
        $medecin->load(['clinique', 'cliniques']);

        return response()->json([
            'message' => 'Médecin mis à jour avec succès',
            'data' => $medecin,
            'photo_url' => $medecin->photo_profil ? asset('storage/' . $medecin->photo_profil) : null
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function profile()
    {
        //
    }

    // Ici on affiche les mises à jour concernant les heures de travail du médecin
    public function updateWorkingHours(Request $request)
    {
        $medecin = Medecin::find(Auth::guard('medecin')->id());

        $request->validate([
            'working_hours' => 'required|array',
            'working_hours.*.day' => 'required|string',
            'working_hours.*.hours' => 'required|string',
        ]);

        $medecin->working_hours = $request->working_hours;
        $medecin->save();

        // Recharger les relations
        $medecin->load(['clinique', 'cliniques']);

        return response()->json([
            'message' => 'Horaires de consultation mis à jour',
            'working_hours' => $medecin->working_hours,
            'medecin' => $medecin
        ]);
    }

    public function checkAvailability($id)
    {
        $medecin = Medecin::find($id);

        if (!$medecin) {
            return response()->json(['message' => 'Médecin non trouvé'], 404);
        }

        $availability = $this->calculateCurrentAvailability($medecin);

        return response()->json([
            'is_available' => $availability['is_available'],
            'status' => $availability['status'],
            'message' => $availability['message'],
            'next_available' => $availability['next_available']
        ]);
    }

    private function calculateCurrentAvailability($medecin)
    {
        $now = now();
        $currentDay = strtolower($now->isoFormat('dddd')); // 'monday', 'tuesday', etc.
        $currentTime = $now->format('H:i');

        $workingHours = $medecin->working_hours ?? [];

        // Si pas d'horaires définis, considérer comme disponible
        if (empty($workingHours)) {
            return [
                'is_available' => true,
                'status' => 'available',
                'message' => 'Disponible',
                'next_available' => null
            ];
        }

        // Trouver les horaires du jour actuel
        $todaySchedule = collect($workingHours)->firstWhere('day', $currentDay);

        if (!$todaySchedule) {
            return $this->getNextAvailability($workingHours, $now);
        }

        // Vérifier si on est dans les heures de travail
        $hours = explode('-', $todaySchedule['hours']);
        if (count($hours) === 2) {
            $startTime = trim($hours[0]);
            $endTime = trim($hours[1]);

            if ($currentTime >= $startTime && $currentTime <= $endTime) {
                return [
                    'is_available' => true,
                    'status' => 'available',
                    'message' => 'Disponible maintenant',
                    'next_available' => null
                ];
            } elseif ($currentTime < $startTime) {
                return [
                    'is_available' => false,
                    'status' => 'later_today',
                    'message' => 'Disponible à ' . $startTime,
                    'next_available' => $startTime
                ];
            }
        }

        return $this->getNextAvailability($workingHours, $now);
    }

    private function getNextAvailability($workingHours, $currentTime)
    {
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $currentDayIndex = array_search(strtolower($currentTime->isoFormat('dddd')), $daysOfWeek);

        // Chercher le prochain jour de travail dans les 7 prochains jours
        for ($i = 1; $i <= 7; $i++) {
            $checkDayIndex = ($currentDayIndex + $i) % 7;
            $checkDay = $daysOfWeek[$checkDayIndex];

            $daySchedule = collect($workingHours)->firstWhere('day', $checkDay);
            if ($daySchedule) {
                $hours = explode('-', $daySchedule['hours']);
                if (count($hours) === 2) {
                    $startTime = trim($hours[0]);
                    $nextDate = $currentTime->copy()->addDays($i)->startOfDay();

                    return [
                        'is_available' => false,
                        'status' => 'next_day',
                        'message' => 'Disponible ' . $this->getFrenchDayName($checkDay),
                        'next_available' => $startTime
                    ];
                }
            }
        }

        return [
            'is_available' => false,
            'status' => 'unavailable',
            'message' => 'Non disponible',
            'next_available' => null
        ];
    }

    private function getFrenchDayName($englishDay)
    {
        $days = [
            'monday' => 'lundi',
            'tuesday' => 'mardi',
            'wednesday' => 'mercredi',
            'thursday' => 'jeudi',
            'friday' => 'vendredi',
            'saturday' => 'samedi',
            'sunday' => 'dimanche'
        ];

        return $days[$englishDay] ?? $englishDay;
    }
}
