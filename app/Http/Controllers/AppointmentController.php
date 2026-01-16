<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Medecin;
use App\Models\Patient;
use App\Mail\AppointmentCreated;
use App\Mail\AppointmentConfirmed;
use App\Mail\AppointmentRejected;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    /**
     * Liste des rendez-vous du patient connecté
     */
    public function index()
    {
        try {
            $patient = Auth::guard('patient')->user();

            if (!$patient) {
                return response()->json(['error' => 'Patient non authentifié'], 401);
            }

            \Log::info('Chargement des rendez-vous pour le patient: ' . $patient->id);

            // Test sans les relations d'abord
            $appointments = Appointment::where('patient_id', $patient->id)
                ->latest('date')
                ->latest('time')
                ->get();

            \Log::info('Nombre de rendez-vous trouvés: ' . $appointments->count());

            $formattedAppointments = $appointments->map(function ($appointment) {
                try {
                    return $this->formatAppointmentForPatient($appointment);
                } catch (\Exception $e) {
                    \Log::error('Erreur formatage rendez-vous ' . $appointment->id . ': ' . $e->getMessage());
                    return [
                        'id' => $appointment->id,
                        'medecin' => 'Médecin non disponible',
                        'specialite' => 'Non spécifié',
                        'date' => $appointment->date,
                        'time' => $appointment->time,
                        'status' => $appointment->status,
                        'consultation_type' => $appointment->consultation_type,
                        'can_cancel' => false,
                    ];
                }
            });

            return response()->json([$formattedAppointments]);
        } catch (\Exception $e) {
            \Log::error('Erreur dans AppointmentController@index: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Erreur interne du serveur: ' . $e->getMessage()], 500);
        }
    }
    /**
     * Création d'un rendez-vous par un patient (avec confirmation automatique)
     */
    public function store(Request $request)
    {
        $patient = Auth::guard('patient')->user();
        if (!$patient) return response()->json(['error' => 'Patient non authentifié'], 401);

        $validated = $request->validate([
            'medecin_id' => 'required|exists:medecins,id',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'consultation_type' => 'required|string|max:255',
        ]);

        $medecin = Medecin::find($validated['medecin_id']);

        //Vérifier si le médecin a des horaires définis
        if (empty($medecin->working_hours)) {
            return response()->json([
                'message' => 'Ce médecin est actuellement indisponible pour des rendez-vous.'
            ], 422);
        }

        $date = Carbon::parse($validated['date']);
        $time = Carbon::parse($validated['time']);

        if (!$this->isValidDate($date)) {
            return response()->json(['message' => 'Date invalide ou hors période de réservation.'], 422);
        }

        if (!$this->isValidTime($time)) {
            return response()->json(['message' => 'Les rendez-vous sont possibles entre 08:00 et 19:30.'], 422);
        }

        // Vérifier les conflits
        $conflict = $this->checkConflicts($validated['medecin_id'], $patient->id, $date, $time);
        if ($conflict) return response()->json($conflict, 409);

        DB::beginTransaction();
        try {
            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'medecin_id' => $validated['medecin_id'],
                'date' => $date->format('Y-m-d'),
                'time' => $time->format('H:i'),
                'consultation_type' => $validated['consultation_type'],
                'status' => 'confirmé', // Statut directement confirmé au lieu de 'en_attente'
                'created_by' => 'patient',
                'confirmed_at' => now(), // Ajouter un timestamp de confirmation
            ]);

            // Chargez les relations avant d'envoyer les emails
            $appointment->load(['patient', 'medecin']);

            // Envoyer les emails après la création du rendez-vous avec gestion d'erreur
            $emailSent = false;
            try {
                \Log::info('Tentative d\'envoi d\'email pour le rendez-vous confirmé: ' . $appointment->id);

                // Email au patient - rendez-vous confirmé directement
                Mail::to($patient->email)->send(new AppointmentCreated($appointment, 'patient', true));
                \Log::info('Email de confirmation envoyé au patient: ' . $patient->email);

                // Email au médecin - notification de nouveau rendez-vous confirmé
                Mail::to($medecin->email)->send(new AppointmentCreated($appointment, 'medecin', true));
                \Log::info('Email de notification envoyé au médecin: ' . $medecin->email);

                $emailSent = true;
            } catch (\Exception $emailException) {
                \Log::error('Erreur envoi email création rendez-vous: ' . $emailException->getMessage());
                \Log::error('Stack trace: ' . $emailException->getTraceAsString());
                // On continue même si l'email échoue
            }

            DB::commit();

            return response()->json([
                'message' => 'Rendez-vous confirmé avec succès.' . ($emailSent ? '' : ' (Problème d\'envoi d\'email)'),
                'appointment' => $this->formatAppointmentForPatient($appointment),
                'email_sent' => $emailSent
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur création rendez-vous: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Erreur interne lors de la création du rendez-vous.'], 500);
        }
    }

    /**
     * Liste des rendez-vous du médecin connecté
     */
    public function indexForDoctor()
    {
        try {
            $medecin = Auth::guard('medecin')->user();

            if (!$medecin) {
                return response()->json(['error' => 'Médecin non authentifié'], 401);
            }

            \Log::info('Chargement des rendez-vous pour le médecin: ' . $medecin->id);

            // Récupérer les rendez-vous du médecin avec les informations du patient
            $appointments = Appointment::where('medecin_id', $medecin->id)
                ->with('patient') // Charger la relation patient
                ->latest('date')
                ->latest('time')
                ->get();

            \Log::info('Nombre de rendez-vous trouvés: ' . $appointments->count());

            $formattedAppointments = $appointments->map(function ($appointment) {
                try {
                    return $this->formatAppointmentForDoctor($appointment);
                } catch (\Exception $e) {
                    \Log::error('Erreur formatage rendez-vous ' . $appointment->id . ': ' . $e->getMessage());
                    return [
                        'id' => $appointment->id,
                        'patient_nom' => 'Patient non disponible',
                        'patient_prenom' => '',
                        'date' => $appointment->date,
                        'time' => $appointment->time,
                        'status' => $appointment->status,
                        'consultation_type' => $appointment->consultation_type,
                    ];
                }
            });

            return response()->json($formattedAppointments);
        } catch (\Exception $e) {
            \Log::error('Erreur dans AppointmentController@indexForDoctor: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'error' => 'Erreur interne du serveur',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Annuler un rendez-vous (patient)
     */
    public function cancel($id)
    {
        $patient = Auth::guard('patient')->user();
        if (!$patient) return response()->json(['error' => 'Patient non authentifié'], 401);

        $appointment = Appointment::where('id', $id)
            ->where('patient_id', $patient->id)
            ->first();

        if (!$appointment) return response()->json(['error' => 'Rendez-vous non trouvé'], 404);

        if (!in_array($appointment->status, ['en_attente', 'confirmé'])) {
            return response()->json(['error' => 'Impossible d’annuler ce rendez-vous.'], 422);
        }

        $dateTime = Carbon::parse($appointment->date . ' ' . $appointment->time);
        if ($dateTime->diffInHours(now()) < 24) {
            return response()->json(['error' => 'Annulation impossible à moins de 24h du rendez-vous.'], 422);
        }

        $appointment->update([
            'status' => 'annulé',
            'cancelled_at' => now(),
            'cancelled_by' => 'patient',
        ]);

        return response()->json(['message' => 'Rendez-vous annulé avec succès.']);
    }

    // === Utilitaires privés === //

    private function checkConflicts($medecinId, $patientId, $date, $time)
    {
        // Patient : pas deux RDV le même jour
        $hasPatientConflict = Appointment::where('patient_id', $patientId)
            ->where('date', $date)
            ->whereIn('status', ['en_attente', 'confirmé'])
            ->exists();

        if ($hasPatientConflict) {
            return ['message' => 'Vous avez déjà un rendez-vous ce jour-là.', 'type' => 'patient'];
        }

        // Médecin : intervalle minimum de 45 min
        $start = $time->copy()->subMinutes(45)->format('H:i');
        $end = $time->copy()->addMinutes(45)->format('H:i');

        $hasDoctorConflict = Appointment::where('medecin_id', $medecinId)
            ->where('date', $date)
            ->whereBetween('time', [$start, $end])
            ->whereIn('status', ['en_attente', 'confirmé'])
            ->exists();

        if ($hasDoctorConflict) {
            return ['message' => 'Le médecin n’est pas disponible à cette heure.', 'type' => 'doctor'];
        }

        return null;
    }

    private function isValidDate(Carbon $date): bool
    {
        return !$date->isSunday() && $date->between(Carbon::today(), Carbon::today()->addMonths(3));
    }

    private function isValidTime(Carbon $time): bool
    {
        return $time->between(Carbon::parse('08:00'), Carbon::parse('19:30'));
    }

    private function formatAppointmentForPatient(Appointment $a)
    {
        return [
            'id' => $a->id,
            'medecin' => $a->medecin ? "Dr. {$a->medecin->prenom} {$a->medecin->nom}" : null,
            'specialite' => $a->medecin->specialite->nom ?? null,
            'date' => $a->date,
            'time' => $a->time,
            'status' => $a->status,
            'consultation_type' => $a->consultation_type,
            'can_cancel' => $this->canBeCancelled($a),
        ];
    }

    private function formatAppointmentForDoctor(Appointment $a)
    {
        $patient = $a->patient;

        return [
            'id' => $a->id,
            'patient' => "{$patient->prenom} {$patient->nom}",
            'patient_id' => $patient->id,
            'patient_nom' => $patient->nom,
            'patient_prenom' => $patient->prenom,
            'patient_email' => $patient->email,
            'patient_telephone' => $patient->telephone,
            'patient_address' => $patient->address,
            'patient_groupe_sanguin' => $patient->groupe_sanguin,
            'patient_serologie_vih' => $patient->serologie_vih,
            'patient_antecedents_medicaux' => $patient->antecedents_medicaux,
            'patient_allergies' => $patient->allergies,
            'patient_traitements_chroniques' => $patient->traitements_chroniques,
            'patient_photo_profil' => $patient->photo_profil,
            'patient_photo_url' => $patient->photo_url,
            'date' => $a->date,
            'time' => $a->time,
            'status' => $a->status,
            'consultation_type' => $a->consultation_type,
            'created_at' => $a->created_at,
            'updated_at' => $a->updated_at,
        ];
    }

    private function canBeCancelled(Appointment $a)
    {
        if (!in_array($a->status, ['en_attente', 'confirmé'])) return false;
        $dateTime = Carbon::parse($a->date . ' ' . $a->time);
        return $dateTime->diffInHours(now()) >= 24;
    }

    /**
     * Récupérer un rendez-vous spécifique pour un médecin
     */
    public function showForDoctor($id)
    {
        try {
            $medecinId = Auth::id();

            $appointment = Appointment::where('id', $id)
                ->where('medecin_id', $medecinId)
                ->with([
                    'patient:id,nom,prenom,email,telephone,address,photo_profil,groupe_sanguin,antecedents_medicaux,allergies,traitements_chroniques',
                    'medecin:id,nom,prenom,specialite'
                ])
                ->firstOrFail();

            // Aplatir les données pour faciliter l'accès côté frontend
            return response()->json([
                'id' => $appointment->id,
                'appointment_id' => $appointment->id,
                'date' => $appointment->date,
                'time' => $appointment->time,
                'consultation_type' => $appointment->consultation_type ?? 'Consultation générale',
                'statut' => $appointment->statut,
                'motif' => $appointment->motif,
                'notes' => $appointment->notes,

                // Informations patient aplatties
                'patient_id' => $appointment->patient->id,
                'patient_nom' => $appointment->patient->nom,
                'patient_prenom' => $appointment->patient->prenom,
                'patient_email' => $appointment->patient->email,
                'patient_telephone' => $appointment->patient->telephone,
                'patient_address' => $appointment->patient->address,
                'patient_photo_profil' => $appointment->patient->photo_profil,
                'patient_groupe_sanguin' => $appointment->patient->groupe_sanguin,
                'patient_antecedents_medicaux' => $appointment->patient->antecedents_medicaux,
                'patient_allergies' => $appointment->patient->allergies,
                'patient_traitements_chroniques' => $appointment->patient->traitements_chroniques,

                // Informations médecin aplatties
                'medecin_id' => $appointment->medecin->id,
                'medecin_nom' => $appointment->medecin->nom,
                'medecin_prenom' => $appointment->medecin->prenom,
                'medecin_specialite' => $appointment->medecin->specialite,

                'created_at' => $appointment->created_at,
                'updated_at' => $appointment->updated_at,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Rendez-vous non trouvé',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function getStats()
    {
        try {
            $patient = Auth::guard('patient')->user();
            if (!$patient) {
                return response()->json(['error' => 'Non authentifié'], 401);
            }

            $now = Carbon::now();

            // On récupère toutes les stats en une seule passe ou presque
            $stats = [
                'total' => Appointment::where('patient_id', $patient->id)->count(),

                'aujourd_hui' => Appointment::where('patient_id', $patient->id)
                    ->whereDate('date', $now->toDateString())
                    ->where('status', 'confirmé')
                    ->count(),

                'a_venir' => Appointment::where('patient_id', $patient->id)
                    ->where('date', '>', $now->toDateString())
                    ->where('status', 'confirmé')
                    ->count(),

                'en_attente' => Appointment::where('patient_id', $patient->id)
                    ->where('status', 'en_attente')
                    ->count(),
            ];

            return response()->json($stats);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getGlobalStats()
    {
        try {
            $now = Carbon::now()->toDateString();

            $stats = [
                // Total historique de la clinique
                'total_historique' => Appointment::count(),

                // Tous les RDV prévus pour aujourd'hui (tous médecins confondus)
                'total_aujourd_hui' => Appointment::whereDate('date', $now)
                    ->where('status', 'confirmé')
                    ->count(),

                // Tous les RDV à venir dans le futur
                'total_a_venir' => Appointment::where('date', '>', $now)
                    ->where('status', 'confirmé')
                    ->count(),

                // Les demandes qui attendent encore une validation
                'total_en_attente' => Appointment::where('status', 'en_attente')
                    ->count(),
            ];

            return response()->json($stats);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
