<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ra_t_alerts', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->string('nom_service', 100)->nullable();
            $table->string('numero_court', 20)->nullable();
            $table->string('keyword', 20)->nullable();
            $table->string('nom_fournisseur', 100)->nullable();
            $table->decimal('seuil_pct', 5, 2)->nullable();
            $table->unsignedBigInteger('count_nb_sms')->nullable();
            $table->string('motif', 255)->nullable();
            $table->boolean('status')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('ra_t_alerts');
    }
};