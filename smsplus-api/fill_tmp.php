echo "Filling ra_t_tmp_occ...\n";
DB::statement("INSERT INTO ra_t_tmp_occ (a_msisdn, b_msisdn, call_type, event_type, charge_amount_orig, subscriber_type, orig_start_time, filename, created_at, updated_at) SELECT a_msisdn, b_msisdn, call_type, event_type, CAST(charge_amount AS VARCHAR), subscriber_type, orig_start_time, 'seeder.csv', created_at, updated_at FROM ra_t_occ_cdr_detail ON CONFLICT DO NOTHING");

echo "Filling ra_t_tmp_mmg...\n";
DB::statement("INSERT INTO ra_t_tmp_mmg (a_msisdn, b_msisdn, event_type, call_type, subscriber_type, service_type, orig_start_time, filename, created_at, updated_at) SELECT a_msisdn, b_msisdn, event_type, 'VOICE' as call_type, 'PREPAID' as subscriber_type, service_type, orig_start_time, 'seeder.xls', created_at, updated_at FROM ra_t_mmg_cdr_det ON CONFLICT DO NOTHING");

echo "Filling ra_t_occ_agg_raw...\n";
DB::statement("INSERT INTO ra_t_occ_agg_raw (b_msisdn, start_date_raw, start_hour, call_type, event_type, subscriber_type, keyword, cdr_count_raw, charge_amount_raw, created_at, updated_at) SELECT b_msisdn, TO_CHAR(start_date, 'DD/MM/YY'), start_hour, call_type, event_type, subscriber_type, keyword, '1' as cdr_count_raw, CAST(charge_amount AS VARCHAR), created_at, updated_at FROM ra_t_occ_cdr_detail ON CONFLICT DO NOTHING");

echo "Filling ra_t_mmg_agg_raw...\n";
DB::statement("INSERT INTO ra_t_mmg_agg_raw (b_msisdn, start_date_raw, start_hour, event_type, call_type, event_status, subscriber_type, service_type, cdr_count_raw, created_at, updated_at) SELECT b_msisdn, TO_CHAR(start_date, 'DD/MM/YY'), start_hour, event_type, 'VOICE' as call_type, 'OK' as event_status, 'PREPAID' as subscriber_type, service_type, '1' as cdr_count_raw, created_at, updated_at FROM ra_t_mmg_cdr_det ON CONFLICT DO NOTHING");

echo "Done.\n";
