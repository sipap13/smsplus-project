<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ra_t_etl_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_name', 128)->index();
            $table->string('job_type', 32)->default('command'); // command|job|export|import
            $table->string('status', 32)->default('pending');   // pending|running|success|failed|timeout
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('rows_processed')->default(0);
            $table->unsignedBigInteger('rows_inserted')->default(0);
            $table->unsignedBigInteger('rows_skipped')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedTinyInteger('pourcentage')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['job_name', 'status']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ra_t_etl_jobs');
    }
};
