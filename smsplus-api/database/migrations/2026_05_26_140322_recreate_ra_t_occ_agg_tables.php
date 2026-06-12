<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ra_t_occ_agg')) {
            Schema::create('ra_t_occ_agg', function (Blueprint $table) {
                $table->id();
                $table->string('b_msisdn', 80)->nullable();
                $table->date('start_date');
                $table->tinyInteger('start_hour')->nullable();
                $table->string('call_type', 20)->nullable();
                $table->string('event_type', 20)->nullable();
                $table->string('subscriber_type', 30)->nullable();
                $table->string('keyword', 50)->nullable();
                $table->unsignedBigInteger('cdr_count')->default(0);
                $table->decimal('charge_amount', 14, 3)->default(0);
                $table->timestamps();

                $table->index(['start_date', 'call_type']);
                $table->index(['start_date', 'keyword']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ra_t_occ_agg');
    }
};
