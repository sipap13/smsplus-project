<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ra_t_occ_cdr_detail', function (Blueprint $table) {
            $table->index('start_date', 'ra_t_occ_cdr_detail_start_date_index');
            $table->index(['start_date', 'call_type'], 'ra_t_occ_cdr_detail_start_date_call_type_index');
        });

        Schema::table('ra_t_mmg_cdr_det', function (Blueprint $table) {
            $table->index('start_date', 'ra_t_mmg_cdr_det_start_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('ra_t_occ_cdr_detail', function (Blueprint $table) {
            $table->dropIndex('ra_t_occ_cdr_detail_start_date_index');
            $table->dropIndex('ra_t_occ_cdr_detail_start_date_call_type_index');
        });

        Schema::table('ra_t_mmg_cdr_det', function (Blueprint $table) {
            $table->dropIndex('ra_t_mmg_cdr_det_start_date_index');
        });
    }
};
