<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportOccAggData extends Command
{
    protected $signature = 'import:occ-agg {path}';
    protected $description = 'Importer le fichier data OCC AGG CSV avec format special pipe-virgule';

    public function handle(): int
    {
        ini_set('memory_limit', '2048M');
        $path = $this->argument('path');

        if (!file_exists($path)) {
            $this->error("Fichier introuvable : $path");
            return self::FAILURE;
        }

        $this->info("Importation du fichier OCC AGG : $path");

        $handle = fopen($path, 'r');
        if (!$handle) {
            $this->error("Impossible d'ouvrir le fichier.");
            return self::FAILURE;
        }

        // Ignorer la premiere ligne (header)
        fgetcsv($handle, 0, ',');

        $batch = [];
        $totalInserted = 0;
        $batchSize = 1000;
        
        DB::beginTransaction();
        try {
            while (($line = fgetcsv($handle, 0, ',')) !== false) {
                // S'il n'y a rien dans la ligne, on passe
                if (!isset($line[0]) || trim($line[0]) === '') {
                    continue;
                }

                $part1 = $line[0];
                $part2 = isset($line[1]) ? trim($line[1]) : '';

                // Enlever les guillemets externes si presents et parser par pipe
                $part1Clean = trim($part1, '"');
                $fields = explode('|', $part1Clean);

                if (count($fields) < 9) {
                    continue; // Ligne invalide
                }

                // Nettoyage de chaque champ
                $b_msisdn = $this->cleanField($fields[0]);
                $start_date_raw = $this->cleanField($fields[1]);
                $start_hour = $this->cleanField($fields[2]);
                $call_type = $this->cleanField($fields[3]);
                $event_type = $this->cleanField($fields[4]);
                $subscriber_type = $this->cleanField($fields[5]);
                $keyword_raw = $this->cleanField($fields[6]);
                
                $cdr_count = $this->cleanField($fields[7]);
                $charge_amount_whole = $this->cleanField($fields[8]);

                // Keyword uppercase
                $keyword = $keyword_raw !== null ? strtoupper($keyword_raw) : null;

                // Reconstitution du montant (partie entière + décimale)
                $charge_amount = $charge_amount_whole;
                if ($part2 !== '') {
                    $charge_amount .= '.' . $part2;
                }
                $charge_amount = (float) $charge_amount;

                // Format de date: 01/10/25 -> 2025-10-01
                $start_date = null;
                if ($start_date_raw) {
                    $parts = explode('/', $start_date_raw);
                    if (count($parts) === 3) {
                        $start_date = '20' . $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                    }
                }

                if (!$start_date || !$b_msisdn) {
                    continue;
                }

                $batch[] = [
                    'b_msisdn' => $b_msisdn,
                    'start_date' => $start_date,
                    'start_hour' => is_numeric($start_hour) ? (int)$start_hour : null,
                    'call_type' => $call_type,
                    'event_type' => $event_type,
                    'subscriber_type' => $subscriber_type,
                    'keyword' => $keyword,
                    'cdr_count' => is_numeric($cdr_count) ? (int)$cdr_count : 0,
                    'charge_amount' => $charge_amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($batch) >= $batchSize) {
                    DB::table('ra_t_occ_agg')->insert($batch);
                    $totalInserted += count($batch);
                    $batch = [];
                }
            }

            if (count($batch) > 0) {
                DB::table('ra_t_occ_agg')->insert($batch);
                $totalInserted += count($batch);
            }

            DB::commit();
            $this->info("Import terminé avec succès. $totalInserted lignes importées dans ra_t_occ_agg.");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Erreur lors de l'importation : " . $e->getMessage());
            return self::FAILURE;
        } finally {
            fclose($handle);
        }

        return self::SUCCESS;
    }

    private function cleanField(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value, '" ');
        if ($value === '' || $value === '_N' || $value === '_UN') {
            return null;
        }
        return $value;
    }
}
