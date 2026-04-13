<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Raw import table (strings as-is from CSV)
        Schema::create('ra_t_mmg_agg_raw', function (Blueprint $table) {
            $table->id();
            $table->string('b_msisdn', 40)->nullable();
            $table->string('start_date_raw', 20)->nullable(); // ex: 01/10/25 (DD/MM/YY)
            $table->tinyInteger('start_hour')->nullable();
            $table->string('event_type', 20)->nullable();
            $table->string('call_type', 20)->nullable();
            $table->string('event_status', 30)->nullable();
            $table->string('subscriber_type', 30)->nullable();
            $table->string('service_type', 50)->nullable();
            $table->string('cdr_count_raw', 40)->nullable();
            $table->timestamps();

            $table->index(['start_date_raw']);
            $table->index(['call_type']);
            $table->index(['event_status']);
            $table->index(['service_type']);
        });

        // Normalized MMG aggregate table
        Schema::create('ra_t_mmg_agg', function (Blueprint $table) {
            $table->id();
            $table->string('b_msisdn', 40)->nullable();
            $table->date('start_date');
            $table->tinyInteger('start_hour')->nullable();
            $table->string('event_type', 20)->nullable();
            $table->string('call_type', 20)->nullable();
            $table->string('event_status', 30)->nullable();
            $table->string('subscriber_type', 30)->nullable();
            $table->string('service_type', 50)->nullable();
            $table->unsignedBigInteger('cdr_count')->default(0);
            $table->timestamps();

            $table->index(['start_date', 'call_type']);
            $table->index(['start_date', 'service_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ra_t_mmg_agg');
        Schema::dropIfExists('ra_t_mmg_agg_raw');
    }
};

