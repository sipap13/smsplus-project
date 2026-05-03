<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;

class DetectFraudAlerts extends Command
{
    protected $signature = 'alerts:detect';
    protected $description = 'Détection automatique des anomalies de volume (SMS suspects) basée sur historique + 2 sigma';

    public function handle(NotificationService $notifier)
    {
        $this->info("Démarrage de la détection des anomalies...");

        // 1. Calcul du seuil de référence (30 derniers jours)
        $historique = DB::table('ra_t_occ_cdr_detail')
            ->where('call_type', 'VAS')
            ->where('start_date', '>=', now()->subDays(30)->toDateString())
            ->where('start_date', '<', now()->toDateString())
            ->selectRaw('keyword, start_date, COUNT(*) as nb_cdr_jour')
            ->groupBy('keyword', 'start_date')
            ->get()
            ->groupBy('keyword');

        // 2. Volume actuel (aujourd'hui)
        $actuel = DB::table('ra_t_occ_cdr_detail')
            ->where('call_type', 'VAS')
            ->where('start_date', '=', now()->toDateString())
            ->selectRaw('keyword, COUNT(*) as nb_cdr_actuel, SUM(charge_amount) as revenus_actuel, COUNT(DISTINCT a_msisdn) as abonnes_actuel')
            ->groupBy('keyword')
            ->get();

        $alertsCreated = 0;

        foreach ($actuel as $v) {
            $keyword = $v->keyword;
            if (!$keyword) continue;

            $stats = $historique->get($keyword);
            if (!$stats || $stats->count() < 2) continue; // Besoin d'au moins 2 jours pour stddev

            $counts = $stats->pluck('nb_cdr_jour');
            $moyenne = $counts->avg();
            
            // Calcul écart-type (σ)
            $sumSq = 0;
            foreach ($counts as $c) {
                $sumSq += pow($c - $moyenne, 2);
            }
            $stdDev = sqrt($sumSq / ($counts->count() - 1));
            
            $seuilNormal = $moyenne + 2 * $stdDev;

            if ($v->nb_cdr_actuel > $seuilNormal) {
                $depassementPct = $moyenne > 0 ? (($v->nb_cdr_actuel - $moyenne) / $moyenne * 100) : 100;

                // N'est suspect QUE si dépassement > 20% (comme demandé par le USER)
                if ($depassementPct < 20) continue;

                // Vérifie si une alerte ouverte existe déjà pour ce keyword aujourd'hui
                $exists = DB::table('ra_t_alerts')
                    ->where('keyword', $keyword)
                    ->where('start_date', now()->toDateString())
                    ->where('status', false)
                    ->exists();

                if (!$exists) {
                    $service = DB::table('ra_t_services')->where('keyword', $keyword)->first();
                    
                    DB::table('ra_t_alerts')->insert([
                        'start_date' => now()->toDateString(),
                        'nom_service' => $service->nom_service ?? 'N/A',
                        'numero_court' => $service->numero_court ?? 'N/A',
                        'keyword' => $keyword,
                        'nom_fournisseur' => $service->nom_fournisseur ?? 'N/A',
                        'seuil_pct' => round($depassementPct, 2),
                        'count_nb_sms' => $v->nb_cdr_actuel,
                        'motif' => "Volume anormal détecté: " . round($depassementPct, 2) . "% au dessus de la moyenne",
                        'status' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $notifier->notifyFraudAlert($keyword, "Alerte de volume critique: " . round($depassementPct, 2) . "% de dépassement");
                    $alertsCreated++;
                }
            }
        }

        $this->info("Terminé. Alertes créées : $alertsCreated");
        return self::SUCCESS;
    }
}
