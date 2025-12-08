<?php
// database/migrations/2024_xx_xx_create_telemedicine_sessions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('telemedicine_sessions', function (Blueprint $table) {
            $table->id();
            
            // Relation avec le rendez-vous
            $table->foreignId('appointment_id')
                  ->constrained('appointments')
                  ->onDelete('cascade');
            
            // Informations de la salle vidéo
            $table->string('room_id')->unique(); // ID unique de la salle
            $table->text('room_url'); // URL complète de la salle
            
            // Horodatages de la session
            $table->timestamp('started_at')->nullable(); // Quand la consultation a commencé
            $table->timestamp('ended_at')->nullable(); // Quand la consultation s'est terminée
            $table->integer('duration_minutes')->nullable(); // Durée en minutes
            
            // Informations supplémentaires
            $table->text('recording_url')->nullable(); // URL de l'enregistrement (si activé)
            $table->text('notes')->nullable(); // Notes du médecin après la consultation
            
            // Participants (pour traçabilité)
            $table->boolean('medecin_joined')->default(false);
            $table->boolean('patient_joined')->default(false);
            $table->timestamp('medecin_joined_at')->nullable();
            $table->timestamp('patient_joined_at')->nullable();
            
            // Qualité de la connexion (optionnel)
            $table->enum('connection_quality', ['excellent', 'good', 'fair', 'poor'])->nullable();
            
            // Statut de la session
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])
                  ->default('pending');
            
            $table->timestamps();
            
            // Index pour les recherches fréquentes
            $table->index('appointment_id');
            $table->index('started_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemedicine_sessions');
    }
};