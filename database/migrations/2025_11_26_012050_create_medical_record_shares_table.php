<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('medical_record_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->onDelete('cascade');
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('medecin_source_id')->constrained('medecins')->onDelete('cascade');
            $table->foreignId('medecin_dest_id')->constrained('medecins')->onDelete('cascade');
            $table->text('reason');
            $table->timestamp('shared_at');
            $table->timestamp('access_expires_at');
            $table->boolean('is_active')->default(true);
            $table->text('access_token')->nullable();
            $table->timestamps();

            // Index pour les performances
            $table->index(['patient_id', 'medecin_dest_id']);
            $table->index('access_expires_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('medical_record_shares');
    }
};