<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ra_t_mmg_occ_ecart', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->unsignedBigInteger('nb_cdr_mmg')->default(0);
            $table->unsignedBigInteger('nb_cdr_occ')->default(0);
            $table->decimal('ecart_pct', 8, 5)->nullable();
            $table->boolean('alerte')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('ra_t_mmg_occ_ecart');
    }
};