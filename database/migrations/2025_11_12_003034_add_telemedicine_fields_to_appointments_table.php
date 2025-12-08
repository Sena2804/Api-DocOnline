<?php
// database/migrations/2024_xx_xx_add_telemedicine_fields_to_appointments_table.php

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
        Schema::table('appointments', function (Blueprint $table) {
            // Mode de consultation : présentiel ou télémédecine
            $table->enum('consultation_mode', ['presentiel', 'telemedicine'])
                  ->default('presentiel')
                  ->after('consultation_type');
            
            // ID de la salle vidéo (pour référence rapide)
            $table->string('video_room_id')->nullable()->after('consultation_mode');
            
            // Horodatages de la session vidéo
            $table->timestamp('video_started_at')->nullable()->after('video_room_id');
            $table->timestamp('video_ended_at')->nullable()->after('video_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'consultation_mode',
                'video_room_id',
                'video_started_at',
                'video_ended_at'
            ]);
        });
    }
};