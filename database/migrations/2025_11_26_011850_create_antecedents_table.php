<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('antecedents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->string('type'); // chirurgical, medical, familial, etc.
            $table->text('description');
            $table->date('date_diagnostic')->nullable();
            $table->text('traitement')->nullable();
            $table->string('statut')->default('actif'); // actif, resolu, chronique
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('antecedents');
    }
};