<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ra_t_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('ra_t_users')->cascadeOnDelete();
            $table->enum('type', ['anomalie', 'import', 'alerte', 'systeme', 'rapport']);
            $table->string('titre', 150);
            $table->text('message');
            $table->json('data')->nullable();
            $table->boolean('lue')->default(false);
            $table->timestamp('lue_at')->nullable();
            $table->enum('priorite', ['basse', 'normale', 'haute', 'critique'])->default('normale');
            $table->string('icon', 50)->nullable();
            $table->string('action_url', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'lue']);
            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'priorite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ra_t_notifications');
    }
};
