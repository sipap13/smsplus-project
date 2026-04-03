<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ra_t_mmg_cdr_det', function (Blueprint $table) {
            $table->id();
            $table->string('ne', 50)->nullable();
            $table->string('a_msisdn', 20);
            $table->string('b_msisdn', 20)->nullable();
            $table->date('start_date');
            $table->tinyInteger('start_hour')->nullable();
            $table->string('event_type', 10)->nullable();
            $table->string('event_type_orig', 100)->nullable();
            $table->string('call_type', 20)->nullable();
            $table->string('event_status', 20)->nullable();
            $table->string('subscriber_type', 30)->nullable();
            $table->string('service_type', 20)->nullable();
            $table->string('orig_start_time', 30)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('ra_t_mmg_cdr_det');
    }
};