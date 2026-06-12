


DB::statement("UPDATE ra_t_occ_cdr_detail SET charge_amount = ROUND(CAST(charge_amount AS NUMERIC) * 0.25, 3) WHERE start_date >= '2026-05-28'");
DB::statement("UPDATE ra_t_occ_agg_raw SET charge_amount_raw = CAST(ROUND(CAST(charge_amount_raw AS NUMERIC) * 0.25, 3) AS VARCHAR) WHERE start_date_raw IN ('28/05/26', '29/05/26', '30/05/26', '31/05/26', '01/06/26', '02/06/26')");

echo "Revenues scaled down successfully.\n";
