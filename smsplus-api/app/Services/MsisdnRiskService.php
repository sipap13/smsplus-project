<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MsisdnRiskService
{
    public function __construct(
        protected EtlMonitorService $monitor,
    ) {}

    /**
     * Analyse le risque d'un MSISDN et enregistre un job ETL pour la visibilité UI.
     */
    public function analyze(string $msisdn): array
    {
        $job = null;
        try {
            $job = $this->monitor->startJob(
                'ai_risk_score',
                'systeme',
                null,
                0,
                ['page' => 'Recherche MSISDN', 'msisdn' => $msisdn]
            );
        } catch (\Exception $e) {
            Log::warning('MsisdnRiskService: Failed to start job', ['error' => $e->getMessage()]);
        }

        // 1. Collecte des données pour l'analyse
        $stats = DB::table('ra_t_occ_cdr_detail')
            ->where('a_msisdn', $msisdn)
            ->selectRaw('
                COUNT(*) as nb_cdrs,
                SUM(charge_amount) as total_revenu,
                COUNT(DISTINCT keyword) as nb_services,
                MAX(charge_amount) as max_charge
            ')
            ->first();

        $reclamations = DB::table('ra_t_reclamations')
            ->where('msisdn', $msisdn)
            ->count();

        // 2. Calcul du score de risque (heuristique simple simulant une IA)
        // Facteurs de risque :
        // - Revenu total élevé (> 50 DT)
        // - Nombre de services élevé (> 5)
        // - Présence de réclamations
        // - Charge max unitaire élevée
        
        $score = 0;
        $reasons = [];

        if ($stats->total_revenu > 50) {
            $score += 30;
            $reasons[] = 'Consommation VAS élevée';
        }
        if ($stats->nb_services > 5) {
            $score += 20;
            $reasons[] = 'Multiples services activés';
        }
        if ($reclamations > 0) {
            $score += 25;
            $reasons[] = 'Historique de réclamations';
        }
        if ($stats->max_charge > 5) {
            $score += 15;
            $reasons[] = 'Transactions unitaires à fort montant';
        }

        $score = min(100, $score);
        
        // Simulation d'un délai pour l'effet "IA" dans l'UI
        usleep(500000); // 500ms

        try {
            if ($job) {
                $this->monitor->finishJob($job, 'success', null, [
                    'score' => $score,
                    'reasons' => $reasons,
                    'processed_rows' => 1
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('MsisdnRiskService: Failed to finish job', ['error' => $e->getMessage()]);
        }

        return [
            'score' => $score,
            'level' => $score > 70 ? 'CRITICAL' : ($score > 40 ? 'WARNING' : 'LOW'),
            'reasons' => $reasons,
        ];
    }
}
