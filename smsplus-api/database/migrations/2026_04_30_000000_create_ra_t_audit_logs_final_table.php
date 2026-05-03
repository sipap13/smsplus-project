<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop if exists to ensure clean state with new schema
        Schema::dropIfExists('ra_t_audit_logs');

        Schema::create('ra_t_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_email', 150)->nullable();
            $table->string('user_role', 30)->nullable();
            $table->string('action', 100);
            $table->string('entite', 50);
            $table->string('entite_id', 50)->nullable();
            $table->text('description');
            $table->json('donnees_avant')->nullable();
            $table->json('donnees_apres')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->enum('statut', ['succes', 'echec', 'warning'])->default('succes');
            $table->timestamp('created_at')->useCurrent();

            // Indexes for faster filtering
            $table->index('user_email');
            $table->index('action');
            $table->index('entite');
            $table->index('statut');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ra_t_audit_logs');
    }
};
