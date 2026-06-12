<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = [
        'aggregation_group', 'apn', 'a_imsi', 'a_msisdn_orig', 'bearer_service',
        'b_datasource', 'b_imsi', 'b_msisdn_orig', 'call_reference', 'cause_for_closing',
        'cdr_search_detail_id', 'cell_id', 'cgi_id_key', 'charge_amnt_step',
        'charge_amount_orig', 'c_num', 'c_num_orig', 'data_volume', 'data_volume_down',
        'data_volume_up', 'duration_step', 'estimated_amount', 'event_duration',
        'event_status', 'event_type_orig', 'filename', 'filter_code', 'imei',
        'last_partial', 'ne', 'partial_seq_id', 'partner_code', 'pgw_address',
        'price_plan_code', 'proc_date', 'proc_hour', 'radio_type', 'rate_code',
        'record_id', 'record_status', 'record_type', 'served_msrn', 'service_id',
        'service_partner', 'service_type', 'sgsn_address', 'sms_centre',
        'start_date_time_home', 'start_time', 'teleservice', 'test_flag',
        'ton_a', 'ton_b', 'ton_c', 'traffic_type', 'trunk_in', 'trunk_out'
    ];

    public function up(): void
    {
        Schema::table('ra_t_occ_cdr_detail', function (Blueprint $table) {
            foreach ($this->columns as $col) {
                if (!Schema::hasColumn('ra_t_occ_cdr_detail', $col)) {
                    $table->string($col, 255)->nullable();
                }
            }
        });

        Schema::table('ra_t_mmg_cdr_det', function (Blueprint $table) {
            foreach ($this->columns as $col) {
                if (!Schema::hasColumn('ra_t_mmg_cdr_det', $col)) {
                    $table->string($col, 255)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('ra_t_occ_cdr_detail', function (Blueprint $table) {
            foreach ($this->columns as $col) {
                if (Schema::hasColumn('ra_t_occ_cdr_detail', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('ra_t_mmg_cdr_det', function (Blueprint $table) {
            foreach ($this->columns as $col) {
                if (Schema::hasColumn('ra_t_mmg_cdr_det', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
