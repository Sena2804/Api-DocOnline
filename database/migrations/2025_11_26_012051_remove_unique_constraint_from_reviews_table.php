<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       Schema::table('reviews', function (Blueprint $table) {
            // 1. On supprime les clés étrangères d'abord
            // Laravel nomme souvent les clés : table_colonne_foreign
            $table->dropForeign(['patient_id']);
            $table->dropForeign(['medecin_id']);

            // 2. Maintenant on peut supprimer la contrainte unique
            $table->dropUnique('reviews_patient_id_medecin_id_unique');

            // 3. On remet les clés étrangères normalement
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('medecin_id')->references('id')->on('medecins')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // Rétablir la contrainte unique si rollback
            $table->unique(['patient_id', 'medecin_id']);
        });
    }
};
