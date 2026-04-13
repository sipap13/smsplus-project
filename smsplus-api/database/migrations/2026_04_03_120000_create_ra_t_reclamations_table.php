<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ra_t_reclamations', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn', 20)->index();
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->string('description', 255);
            $table->string('statut', 20)->default('ouverte');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ra_t_reclamations');
    }
};

