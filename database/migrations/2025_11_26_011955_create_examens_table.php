<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('examens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('medecin_id')->constrained()->onDelete('cascade');
            $table->string('type'); // radiologie, biologie, etc.
            $table->text('description');
            $table->date('date_prescription');
            $table->date('date_realisation')->nullable();
            $table->text('resultat')->nullable();
            $table->text('observations')->nullable();
            $table->string('fichier_joint')->nullable();
            $table->enum('statut', ['prescrit', 'en_cours', 'termine', 'annule'])->default('prescrit');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('examens');
    }
};