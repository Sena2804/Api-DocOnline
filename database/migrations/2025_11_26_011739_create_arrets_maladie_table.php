<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('arrets_maladie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->onDelete('cascade');
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('medecin_id')->constrained()->onDelete('cascade');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->integer('duree_jours');
            $table->text('motif');
            $table->text('diagnostic');
            $table->text('recommandations')->nullable();
            $table->boolean('renouvelable')->default(false);
            $table->date('date_visite_controle')->nullable();
            $table->enum('statut', ['actif', 'termine', 'annule'])->default('actif');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('arrets_maladie');
    }
};