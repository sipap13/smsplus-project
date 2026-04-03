<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ra_t_users', function (Blueprint $table) {
            $table->id();
            $table->string('email', 150)->unique();
            $table->string('password', 255);
            $table->string('direction', 100)->nullable();
            $table->enum('role', ['ADMIN', 'ANALYSTE_OP', 'ANALYSTE_BUSS']);
            $table->string('image', 255)->nullable();
            $table->string('tel', 20)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('ra_t_users');
    }
};