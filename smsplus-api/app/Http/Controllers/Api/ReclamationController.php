<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reclamation;
use App\Services\EtlMonitorService;
use Illuminate\Support\Facades\Log;

class ReclamationController extends Controller
{
    public function __construct(
        protected EtlMonitorService $monitor,
    ) {}
    public function byMsisdn($msisdn)
    {
        // Normaliser le MSISDN (cohérent avec CdrController)
        $msisdn = ltrim(preg_replace('/\s+/', '', $msisdn), '+');

        // Job recherche OCC
        $jobIdOcc = null;
        try {
            $jobIdOcc = $this->monitor->startJob(
                'msisdn_search_occ',
                'systeme',
                null,
                0,
                ['page' => 'Recherche MSISDN', 'msisdn' => $msisdn]
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for msisdn_search_occ', ['error' => $e->getMessage()]);
        }

        // Simulation de recherche OCC (à adapter selon votre logique)
        $occResults = []; // Remplacer par vraie requête OCC
        
        try {
            if ($jobIdOcc) {
                $this->monitor->finishJob($jobIdOcc, 'success', null, [
                    'msisdn' => $msisdn,
                    'nb_resultats' => count($occResults),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for msisdn_search_occ', ['error' => $e->getMessage()]);
        }

        // Job recherche MMG
        $jobIdMmg = null;
        try {
            $jobIdMmg = $this->monitor->startJob(
                'msisdn_search_mmg',
                'systeme',
                null,
                0,
                ['page' => 'Recherche MSISDN', 'msisdn' => $msisdn]
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for msisdn_search_mmg', ['error' => $e->getMessage()]);
        }

        // Simulation de recherche MMG (à adapter selon votre logique)
        $mmgResults = []; // Remplacer par vraie requête MMG
        
        try {
            if ($jobIdMmg) {
                $this->monitor->finishJob($jobIdMmg, 'success', null, [
                    'msisdn' => $msisdn,
                    'nb_resultats' => count($mmgResults),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for msisdn_search_mmg', ['error' => $e->getMessage()]);
        }

        // Job recherche réclamations
        $jobIdReclamations = null;
        try {
            $jobIdReclamations = $this->monitor->startJob(
                'msisdn_reclamations_search',
                'systeme',
                null,
                0,
                ['page' => 'Recherche MSISDN', 'msisdn' => $msisdn]
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for msisdn_reclamations_search', ['error' => $e->getMessage()]);
        }

        $reclamations = Reclamation::with('service')
            ->where('msisdn', $msisdn)
            ->orderBy('created_at', 'desc')
            ->get();

        try {
            if ($jobIdReclamations) {
                $this->monitor->finishJob($jobIdReclamations, 'success', null, [
                    'msisdn' => $msisdn,
                    'nb_reclamations' => $reclamations->count(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for msisdn_reclamations_search', ['error' => $e->getMessage()]);
        }

        return response()->json($reclamations);
    }
}