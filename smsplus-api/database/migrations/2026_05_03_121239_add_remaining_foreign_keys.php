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
        // Ajouter FK pour ra_t_api_tokens.user_id -> ra_t_users.id
        Schema::table('ra_t_api_tokens', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('ra_t_users')->onDelete('cascade');
        });

        // Ajouter FK pour ra_t_login_logs.user_id -> ra_t_users.id
        Schema::table('ra_t_login_logs', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('ra_t_users')->onDelete('cascade');
        });

        // Ajouter FK pour ra_t_api_request_metrics.user_id -> ra_t_users.id
        Schema::table('ra_t_api_request_metrics', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('ra_t_users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer FK pour ra_t_api_tokens
        Schema::table('ra_t_api_tokens', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        // Supprimer FK pour ra_t_login_logs
        Schema::table('ra_t_login_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        // Supprimer FK pour ra_t_api_request_metrics
        Schema::table('ra_t_api_request_metrics', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
};
