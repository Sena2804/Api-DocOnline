<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\PatientAuthController;
use App\Http\Controllers\Auth\MedecinAuthController;
use App\Http\Controllers\Auth\CliniqueAuthController;
use App\Http\Controllers\MedecinController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\Api\MedicalChatController;
use App\Http\Controllers\Auth\GlobalAuthController;
use App\Http\Controllers\CliniqueController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\FavoriteController;

// ======================
// Routes Public
// ======================
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Route pour la connexio en global
Route::post('/login', [GlobalAuthController::class, 'login']);

// Route pour la déconnexion protégée par le middleware
Route::middleware('auth:sanctum')->post('/logout', [GlobalAuthController::class, 'logout']);

Route::middleware('auth:sanctum')->group(function () {
    // Route pour récupérer tous les utilisateurs
    Route::get('/users', [GlobalAuthController::class, 'getAllUsers']);
});

Route::get('/admin/stats/global', [AppointmentController::class, 'getGlobalStats'])->middleware('auth:sanctum');

// Récupérer tous les médecins
Route::get('/medecins', [MedecinController::class, 'index']);
Route::get('/medecins/{id}', [MedecinController::class, 'show']);

// Récupérer tous les médecins disponibles à une date donnée
Route::get('/medecins/{id}/availability', [MedecinController::class, 'checkAvailability']);

// Récupérer toutes les cliniques
Route::get('/cliniques', [CliniqueController::class, 'index']);
Route::get('/cliniques/{id}', [CliniqueController::class, 'show']);

// Routes pour l'IA
Route::post('/chat/diagnose', [MedicalChatController::class, 'diagnose']);
Route::post('/chat/voice', [MedicalChatController::class, 'voiceMessage']);

// ======================
// Routes Favoris
// ======================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/{medecinId}', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{medecinId}', [FavoriteController::class, 'destroy']);
    Route::get('/favorites/check/{medecinId}', [FavoriteController::class, 'check']);
});

// ======================
// Routes Patient
// ======================
Route::prefix('patient')->group(function () {
    Route::post('/register', [PatientAuthController::class, 'register']);
    Route::post('/login', [PatientAuthController::class, 'login']);

    // Routes sécurisées pour le profil patient
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [PatientAuthController::class, 'profile']);
        Route::put('/profile', [PatientAuthController::class, 'updateProfile']);
        Route::put('/profile/password', [PatientAuthController::class, 'updatePassword']);
        Route::delete('/profile', [PatientAuthController::class, 'deleteAccount']);
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::delete('/appointments/{id}', [AppointmentController::class, 'cancel']);
        Route::get('/', [PatientAuthController::class, 'index']);
        Route::get('/appointments/stats', [AppointmentController::class, 'getStats']);

        // Ajouter cette route pour l'upload de photo
        Route::post('/profile/photo', [PatientAuthController::class, 'updatePhoto']);

        // NOUVELLES ROUTES POUR LE DOSSIER MEDICAL PATIENT
        Route::get('/dossier-medical', [PatientAuthController::class, 'getDossierMedical']);
        Route::get('/ordonnances', [PatientAuthController::class, 'getOrdonnances']);
        Route::get('/arrets-maladie', [PatientAuthController::class, 'getArretsMaladie']);
    });
});

// ======================
// Routes Médecin
// ======================
Route::prefix('medecin')->group(function () {
    Route::post('/register', [MedecinAuthController::class, 'register']);
    Route::post('/login', [MedecinAuthController::class, 'login']);

    // Routes sécurisées pour le profil medecin
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [MedecinAuthController::class, 'profile']);
        Route::put('/profile', [MedecinAuthController::class, 'updateProfile']);
        Route::put('/profile/password', [MedecinAuthController::class, 'updatePassword']);
        Route::delete('/profile', [MedecinAuthController::class, 'deleteAccount']);
        Route::delete('/{id}', [MedecinAuthController::class, 'deleteMedecin']);
        Route::post('/profile/photo', [MedecinAuthController::class, 'updatePhoto']);
        Route::put('/working-hours', [MedecinAuthController::class, 'updateWorkingHours']);
        Route::get('/appointments', [AppointmentController::class, 'doctorAppointments']);
        Route::get('/appointments', [AppointmentController::class, 'indexForDoctor']);
        Route::patch('/appointments/{id}/confirm', [AppointmentController::class, 'confirm']);
        Route::patch('/appointments/{id}/reject', [AppointmentController::class, 'reject']);

        // ROUTE POUR RÉCUPÉRER UN RDV SPÉCIFIQUE
        Route::get('/appointments/{id}', [AppointmentController::class, 'showForDoctor']);

        // NOUVELLES ROUTES POUR LES FONCTIONNALITES MEDICALES
        Route::get('/dossier-medical/{patientId}', [MedecinAuthController::class, 'getDossierMedicalPatient']);
        Route::post('/ordonnances', [MedecinAuthController::class, 'createOrdonnance']);
        Route::post('/arrets-maladie', [MedecinAuthController::class, 'createArretMaladie']);
        Route::post('/share-medical-record', [MedecinAuthController::class, 'shareMedicalRecord']);

        // Gestion des ordonnances et arrêts maladie
        Route::get('/ordonnances', [MedecinAuthController::class, 'getOrdonnances']);
        Route::get('/arrets-maladie', [MedecinAuthController::class, 'getArretsMaladie']);
        Route::get('/ordonnances/{id}', [MedecinAuthController::class, 'getOrdonnance']);
        Route::get('/arrets-maladie/{id}', [MedecinAuthController::class, 'getArretMaladie']);
    });
});

// ======================
// Routes Clinique
// ======================
Route::prefix('clinique')->group(function () {
    Route::post('/register', [CliniqueAuthController::class, 'register']);
    Route::post('/login', [CliniqueAuthController::class, 'login']);

    // Routes sécurisées pour la clinique
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [CliniqueAuthController::class, 'profile']);
        Route::put('/profile', [CliniqueAuthController::class, 'updateProfile']);
        Route::put('/profile/password', [CliniqueAuthController::class, 'updatePassword']);
        Route::delete('/profile', [CliniqueAuthController::class, 'deleteAccount']);
        Route::post('/profile/photo', [CliniqueAuthController::class, 'updatePhoto']);
        Route::get('/', [CliniqueAuthController::class, 'index']);

        // Gestion des médecins
        Route::get('/medecins', [CliniqueAuthController::class, 'getMedecins']);
        Route::post('/medecins/add', [CliniqueAuthController::class, 'addMedecin']);
        Route::delete('/medecins/{medecinId}', [CliniqueAuthController::class, 'removeMedecin']);
    });
});

// ======================
// Routes Rendez-vous
// ======================
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/appointments', [AppointmentController::class, 'store']);
});

// ======================
// Routes Messages
// ======================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/messages/{medecinId}', [MessageController::class, 'getMessages']);
    Route::post('/messages/send', [MessageController::class, 'sendMessage']);
});

// ======================
// Routes Avis et Notes
// ======================

// Routes publiques - accessibles sans authentification
Route::get('/medecins/{id}/reviews', [ReviewController::class, 'index']);
Route::get('/medecins/{id}/reviews/stats', [ReviewController::class, 'stats']);

// Routes protégées - nécessitent une authentification
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/medecins/{id}/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{id}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
});

// ======================
// Routes Google OAuth
// ======================
Route::prefix('auth/google')->group(function () {
    Route::post('/patient', [PatientAuthController::class, 'googleAuth']);
    Route::post('/medecin', [MedecinAuthController::class, 'googleAuth']);
    Route::post('/clinique', [CliniqueAuthController::class, 'googleAuth']);
});

// ======================
// NOUVELLES ROUTES POUR LES FONCTIONNALITES MEDICALES
// ======================

// Routes pour le partage de dossier médical
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/api/share-medical-record', [MedecinAuthController::class, 'shareMedicalRecord']);
    Route::get('/api/medecins', [MedecinAuthController::class, 'getAllMedecinsForSharing']);
});

// Routes pour les ordonnances (accessibles par les patients et médecins)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/api/ordonnances', [MedecinAuthController::class, 'getOrdonnances']);
    Route::get('/api/ordonnances/{id}', [MedecinAuthController::class, 'getOrdonnance']);
    Route::post('/api/ordonnances', [MedecinAuthController::class, 'createOrdonnance']);
});

// Routes pour les arrêts maladie (accessibles par les patients et médecins)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/api/arrets-maladie', [MedecinAuthController::class, 'getArretsMaladie']);
    Route::get('/api/arrets-maladie/{id}', [MedecinAuthController::class, 'getArretMaladie']);
    Route::post('/api/arrets-maladie', [MedecinAuthController::class, 'createArretMaladie']);
});

// Routes pour le dossier médical complet
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/api/dossier-medical/{patientId}', [MedecinAuthController::class, 'getDossierMedicalPatient']);
    Route::get('/api/patient/dossier-medical', [PatientAuthController::class, 'getDossierMedical']);
});

// Routes Médecin existantes (compléter avec les nouvelles)
Route::prefix('medecin')->middleware('auth:sanctum')->group(function () {
    // ... routes existantes ...

    // NOUVELLES ROUTES POUR LES FONCTIONNALITES MEDICALES
    Route::get('/dossier-medical/{patientId}', [MedecinAuthController::class, 'getDossierMedicalPatient']);
    Route::post('/ordonnances', [MedecinAuthController::class, 'createOrdonnance']);
    Route::post('/arrets-maladie', [MedecinAuthController::class, 'createArretMaladie']);
    Route::post('/share-medical-record', [MedecinAuthController::class, 'shareMedicalRecord']);
    Route::get('/ordonnances', [MedecinAuthController::class, 'getOrdonnances']);
    Route::get('/arrets-maladie', [MedecinAuthController::class, 'getArretsMaladie']);
});

// Routes Patient existantes (compléter avec les nouvelles)
Route::prefix('patient')->middleware('auth:sanctum')->group(function () {
    // ... routes existantes ...

    // NOUVELLES ROUTES POUR LE DOSSIER MEDICAL PATIENT
    Route::get('/dossier-medical', [PatientAuthController::class, 'getDossierMedical']);
    Route::get('/ordonnances', [PatientAuthController::class, 'getOrdonnances']);
    Route::get('/arrets-maladie', [PatientAuthController::class, 'getArretsMaladie']);
});
