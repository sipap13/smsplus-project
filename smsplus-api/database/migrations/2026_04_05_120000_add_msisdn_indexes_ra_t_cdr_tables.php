<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ra_t_occ_cdr_detail', function (Blueprint $table) {
            $table->index('a_msisdn', 'ra_t_occ_cdr_detail_a_msisdn_index');
            $table->index('b_msisdn', 'ra_t_occ_cdr_detail_b_msisdn_index');
        });

        Schema::table('ra_t_mmg_cdr_det', function (Blueprint $table) {
            $table->index('a_msisdn', 'ra_t_mmg_cdr_det_a_msisdn_index');
            $table->index('b_msisdn', 'ra_t_mmg_cdr_det_b_msisdn_index');
        });
    }

    public function down(): void
    {
        Schema::table('ra_t_occ_cdr_detail', function (Blueprint $table) {
            $table->dropIndex('ra_t_occ_cdr_detail_a_msisdn_index');
            $table->dropIndex('ra_t_occ_cdr_detail_b_msisdn_index');
        });

        Schema::table('ra_t_mmg_cdr_det', function (Blueprint $table) {
            $table->dropIndex('ra_t_mmg_cdr_det_a_msisdn_index');
            $table->dropIndex('ra_t_mmg_cdr_det_b_msisdn_index');
        });
    }
};
