<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ra_t_services', function (Blueprint $table) {
            $table->id();
            $table->string('nom_fournisseur', 100);
            $table->string('nom_service', 100);
            $table->string('numero_court', 20);
            $table->string('keyword', 20);
            $table->enum('type_service', ['Service', 'jeu'])->nullable();
            $table->decimal('prix', 10, 3)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('ra_t_services');
    }
};