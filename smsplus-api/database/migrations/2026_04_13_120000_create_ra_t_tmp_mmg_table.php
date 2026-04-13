<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ra_t_tmp_mmg', function (Blueprint $table) {
            $table->id();

            // Raw columns (subset needed for mapping)
            $table->string('ne', 50)->nullable();
            $table->string('a_msisdn', 40)->nullable();
            $table->string('b_msisdn', 40)->nullable();
            $table->string('event_type', 20)->nullable();
            $table->string('event_type_orig', 150)->nullable();
            $table->string('call_type', 30)->nullable();
            $table->string('event_status', 30)->nullable();
            $table->string('subscriber_type', 30)->nullable();
            $table->string('service_type', 50)->nullable();
            $table->string('orig_start_time', 40)->nullable();
            $table->string('filename', 255)->nullable();
            $table->string('traffic_type', 20)->nullable();

            // PROC_DATE format: YYYYMMDDHHmmss (ex: 20260406000000) stored as string
            $table->string('proc_date', 20)->nullable();
            $table->tinyInteger('proc_hour')->nullable();

            $table->string('partner', 50)->nullable();

            $table->timestamps();

            $table->index(['proc_date', 'proc_hour']);
            $table->index(['event_status']);
            $table->index(['call_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ra_t_tmp_mmg');
    }
};

