<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ajouter FK pour ra_t_reclamations.service_id -> ra_t_services.id
        Schema::table('ra_t_reclamations', function (Blueprint $table) {
            $table->foreign('service_id')->references('id')->on('ra_t_services')->onDelete('cascade');
        });

        // Ajouter FK pour ra_t_audit_logs.user_id -> ra_t_users.id
        Schema::table('ra_t_audit_logs', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('ra_t_users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer FK pour ra_t_reclamations
        Schema::table('ra_t_reclamations', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
        });

        // Supprimer FK pour ra_t_audit_logs
        Schema::table('ra_t_audit_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
};
