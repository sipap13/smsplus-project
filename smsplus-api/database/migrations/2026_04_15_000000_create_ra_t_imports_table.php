<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ra_t_imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->enum('type', ['occ', 'mmg']);
            $table->enum('status', ['pending', 'processing', 'done', 'error'])->default('pending');
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedBigInteger('imported_rows')->default(0);
            $table->unsignedBigInteger('error_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->string('imported_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ra_t_imports');
    }
};
