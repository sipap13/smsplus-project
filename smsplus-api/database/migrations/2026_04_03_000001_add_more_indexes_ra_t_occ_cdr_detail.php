<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ra_t_occ_cdr_detail', function (Blueprint $table) {
            $table->index('call_type', 'ra_t_occ_cdr_detail_call_type_index');
            $table->index('start_hour', 'ra_t_occ_cdr_detail_start_hour_index');
            $table->index(['start_date', 'start_hour'], 'ra_t_occ_cdr_detail_start_date_start_hour_index');
        });
    }

    public function down(): void
    {
        Schema::table('ra_t_occ_cdr_detail', function (Blueprint $table) {
            $table->dropIndex('ra_t_occ_cdr_detail_call_type_index');
            $table->dropIndex('ra_t_occ_cdr_detail_start_hour_index');
            $table->dropIndex('ra_t_occ_cdr_detail_start_date_start_hour_index');
        });
    }
};

