<?php
// app/Http/Controllers/TelemedicineController.php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\TelemedicineSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TelemedicineController extends Controller
{
    /**
     * Créer ou récupérer une session de télémédecine
     */
    public function createSession($appointmentId)
    {
        try {
            // Récupérer l'utilisateur connecté (médecin ou patient)
            $medecin = Auth::guard('medecin')->user();
            $patient = Auth::guard('patient')->user();
            
            $appointment = Appointment::with(['patient', 'medecin'])
                ->findOrFail($appointmentId);

            // Vérifier les autorisations
            if ($medecin && $appointment->medecin_id !== $medecin->id) {
                return response()->json(['error' => 'Non autorisé'], 403);
            }

            if ($patient && $appointment->patient_id !== $patient->id) {
                return response()->json(['error' => 'Non autorisé'], 403);
            }

            // Vérifier que c'est une téléconsultation
            if ($appointment->consultation_mode !== 'telemedicine') {
                return response()->json([
                    'error' => 'Ce rendez-vous n\'est pas configuré pour la télémédecine'
                ], 422);
            }

            // Vérifier que le RDV est confirmé
            if ($appointment->status !== 'confirmé') {
                return response()->json([
                    'error' => 'Le rendez-vous doit être confirmé'
                ], 422);
            }

            // Récupérer ou créer la session
            $session = TelemedicineSession::firstOrCreate(
                ['appointment_id' => $appointmentId],
                [
                    'room_id' => 'meetmedpro-' . Str::random(20),
                    'room_url' => '', // Sera rempli après
                    'status' => 'pending',
                ]
            );

            // Construire l'URL Jitsi
            if (empty($session->room_url)) {
                $session->room_url = "https://meet.jit.si/{$session->room_id}";
                $session->save();
            }

            // Mettre à jour l'appointment avec le room_id
            if (!$appointment->video_room_id) {
                $appointment->update(['video_room_id' => $session->room_id]);
            }

            return response()->json([
                'session' => $session,
                'appointment' => $appointment,
                'can_start' => $this->canStartSession($appointment),
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur création session télémédecine: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'error' => 'Erreur lors de la création de la session',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Démarrer une session
     */
    public function startSession(Request $request, $sessionId)
    {
        try {
            $session = TelemedicineSession::with('appointment')->findOrFail($sessionId);
            
            // Vérifier les autorisations
            $this->authorizeSession($session);

            // Déterminer qui rejoint
            $participantType = Auth::guard('medecin')->check() ? 'medecin' : 'patient';

            // Marquer le participant comme ayant rejoint
            $session->markParticipantJoined($participantType);

            // Si c'est le premier à rejoindre, démarrer la session
            if (!$session->started_at) {
                $session->markAsStarted();
                
                // Mettre à jour l'appointment
                $session->appointment->update([
                    'video_started_at' => now()
                ]);
            }

            return response()->json([
                'message' => 'Session démarrée',
                'session' => $session->fresh(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur démarrage session: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors du démarrage de la session'
            ], 500);
        }
    }

    /**
     * Terminer une session
     */
    public function endSession(Request $request, $sessionId)
    {
        try {
            $session = TelemedicineSession::with('appointment')->findOrFail($sessionId);
            
            // Vérifier les autorisations
            $this->authorizeSession($session);

            // Valider les notes (optionnelles)
            $validated = $request->validate([
                'notes' => 'nullable|string|max:5000',
                'connection_quality' => 'nullable|in:excellent,good,fair,poor',
            ]);

            // Terminer la session
            $session->markAsCompleted($validated['notes'] ?? null);

            // Mettre à jour la qualité de connexion si fournie
            if (isset($validated['connection_quality'])) {
                $session->update(['connection_quality' => $validated['connection_quality']]);
            }

            // Mettre à jour l'appointment
            $session->appointment->update([
                'video_ended_at' => now()
            ]);

            return response()->json([
                'message' => 'Session terminée',
                'session' => $session->fresh(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur fin de session: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la fin de la session'
            ], 500);
        }
    }

    /**
     * Récupérer les informations d'une session
     */
    public function getSession($appointmentId)
    {
        try {
            $session = TelemedicineSession::where('appointment_id', $appointmentId)
                ->with('appointment')
                ->firstOrFail();

            // Vérifier les autorisations
            $this->authorizeSession($session);

            return response()->json($session);

        } catch (\Exception $e) {
            \Log::error('Erreur récupération session: ' . $e->getMessage());
            return response()->json([
                'error' => 'Session non trouvée'
            ], 404);
        }
    }

    /**
     * Vérifier si la session peut être démarrée
     */
    private function canStartSession(Appointment $appointment): bool
    {
        $appointmentDateTime = Carbon::parse($appointment->date . ' ' . $appointment->time);
        $now = now();

        // Autoriser 15 minutes avant et jusqu'à 2 heures après
        $canStartAt = $appointmentDateTime->copy()->subMinutes(15);
        $canStartUntil = $appointmentDateTime->copy()->addHours(2);

        return $now->between($canStartAt, $canStartUntil);
    }

    /**
     * Vérifier les autorisations pour une session
     */
    private function authorizeSession(TelemedicineSession $session): void
    {
        $medecin = Auth::guard('medecin')->user();
        $patient = Auth::guard('patient')->user();

        $appointment = $session->appointment;

        $isAuthorized = false;

        if ($medecin && $appointment->medecin_id === $medecin->id) {
            $isAuthorized = true;
        }

        if ($patient && $appointment->patient_id === $patient->id) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            abort(403, 'Non autorisé à accéder à cette session');
        }
    }

    /**
     * Obtenir l'historique des sessions pour un médecin
     */
    public function getMedecinSessions()
    {
        try {
            $medecin = Auth::guard('medecin')->user();

            $sessions = TelemedicineSession::whereHas('appointment', function ($query) use ($medecin) {
                $query->where('medecin_id', $medecin->id);
            })
            ->with(['appointment.patient'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

            return response()->json($sessions);

        } catch (\Exception $e) {
            \Log::error('Erreur récupération historique sessions: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération de l\'historique'
            ], 500);
        }
    }

    /**
     * Obtenir l'historique des sessions pour un patient
     */
    public function getPatientSessions()
    {
        try {
            $patient = Auth::guard('patient')->user();

            $sessions = TelemedicineSession::whereHas('appointment', function ($query) use ($patient) {
                $query->where('patient_id', $patient->id);
            })
            ->with(['appointment.medecin'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

            return response()->json($sessions);

        } catch (\Exception $e) {
            \Log::error('Erreur récupération historique sessions: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération de l\'historique'
            ], 500);
        }
    }
}