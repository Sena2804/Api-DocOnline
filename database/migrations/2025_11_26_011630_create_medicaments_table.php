<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('medicaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordonnance_id')->constrained()->onDelete('cascade');
            $table->string('nom');
            $table->string('dosage');
            $table->string('posologie');
            $table->string('duree');
            $table->string('quantite');
            $table->text('instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('medicaments');
    }
};