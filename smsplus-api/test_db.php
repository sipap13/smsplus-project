<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$res = DB::select("
SELECT 'ra_t_occ_cdr_detail' as table_name, COUNT(*) as total_lignes, SUM(charge_amount) as total_revenus, COUNT(DISTINCT a_msisdn) as msisdns_uniques, COUNT(DISTINCT keyword) as keywords_uniques, MIN(start_date) as date_min, MAX(start_date) as date_max, COUNT(DISTINCT start_date) as nb_jours FROM ra_t_occ_cdr_detail WHERE call_type = 'VAS' 
UNION ALL 
SELECT 'ra_t_mmg_cdr_det', COUNT(*), 0, COUNT(DISTINCT a_msisdn), COUNT(DISTINCT service_type), MIN(start_date), MAX(start_date), COUNT(DISTINCT start_date) FROM ra_t_mmg_cdr_det 
UNION ALL 
SELECT 'ra_t_services', COUNT(*), SUM(prix), 0, COUNT(DISTINCT keyword), MIN(created_at::date), MAX(created_at::date), 0 FROM ra_t_services 
UNION ALL 
SELECT 'ra_t_alerts', COUNT(*), SUM(count_nb_sms), 0, COUNT(DISTINCT keyword), MIN(start_date), MAX(start_date), 0 FROM ra_t_alerts 
UNION ALL 
SELECT 'ra_t_users', COUNT(*), 0, 0, COUNT(DISTINCT role), MIN(created_at::date), MAX(created_at::date), 0 FROM ra_t_users 
UNION ALL 
SELECT 'ra_t_etl_jobs', COUNT(*), 0, 0, COUNT(DISTINCT job_name), MIN(created_at::date), MAX(created_at::date), 0 FROM ra_t_etl_jobs;
");

print_r($res);

$testDataOcc = DB::select("SELECT 'OCC - MSISDNs test' as source, a_msisdn, COUNT(*) as nb FROM ra_t_occ_cdr_detail WHERE a_msisdn LIKE '%test%' OR a_msisdn LIKE '%123456789%' OR a_msisdn = '21600000000' OR LENGTH(a_msisdn) != 11 GROUP BY a_msisdn LIMIT 10;");
print_r($testDataOcc);

$testDataUsers = DB::select("SELECT 'Users test' as source, email, role FROM ra_t_users WHERE email LIKE '%test%' OR email LIKE '%example%' OR email LIKE '%fake%' OR email = 'test@example.com';");
print_r($testDataUsers);

$testDataServices = DB::select("SELECT 'Services test' as source, keyword, nom_service FROM ra_t_services WHERE keyword LIKE '%test%' OR nom_service LIKE '%Test%' OR nom_service LIKE '%Fake%';");
print_r($testDataServices);

$seedersOcc = DB::select("SELECT COUNT(*) as nb_seeders_occ FROM ra_t_occ_cdr_detail WHERE a_msisdn IN ('21698542320', '21696776950', '21698123456', '21691234567', '21697654321', '21698765432');");
print_r($seedersOcc);
