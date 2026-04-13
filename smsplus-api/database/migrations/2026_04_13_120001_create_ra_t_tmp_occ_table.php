<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ra_t_tmp_occ', function (Blueprint $table) {
            $table->id();

            // Raw columns (subset needed for mapping)
            $table->string('a_msisdn', 40)->nullable();
            $table->string('b_msisdn', 80)->nullable();
            $table->string('call_type', 30)->nullable();
            $table->string('event_type', 30)->nullable();

            // CHARGE_AMOUNT_ORIG can contain comma decimal -> store as string then normalize in ETL
            $table->string('charge_amount_orig', 40)->nullable();

            $table->string('subscriber_type', 30)->nullable();
            $table->string('orig_start_time', 60)->nullable();
            $table->string('filename', 255)->nullable();
            $table->string('traffic_type', 20)->nullable();
            $table->string('partner', 50)->nullable();

            // PROC_DATE format: YYYYMMDDHHmmss (ex: 20260406000000) stored as string
            $table->string('proc_date', 20)->nullable();
            $table->tinyInteger('proc_hour')->nullable();

            // From OCC raw: NE -> datasource
            $table->string('ne', 50)->nullable();

            // Filter code: 0 charged, 1 free
            $table->tinyInteger('filter_code')->nullable();

            $table->timestamps();

            $table->index(['proc_date', 'proc_hour']);
            $table->index(['filter_code']);
            $table->index(['call_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ra_t_tmp_occ');
    }
};

