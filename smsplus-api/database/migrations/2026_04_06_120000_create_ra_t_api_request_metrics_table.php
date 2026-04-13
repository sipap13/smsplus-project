<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ra_t_api_request_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255);
            $table->string('method', 10);
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('role', 30)->nullable();
            $table->string('error_class', 150)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at', 'ra_t_api_metrics_created_at_index');
            $table->index(['path', 'created_at'], 'ra_t_api_metrics_path_created_at_index');
            $table->index(['status_code', 'created_at'], 'ra_t_api_metrics_status_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ra_t_api_request_metrics');
    }
};
