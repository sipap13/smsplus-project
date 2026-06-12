<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EtlMonitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServiceController extends Controller
{
    public function __construct(
        protected EtlMonitorService $monitor,
        protected \App\Services\AuditLogService $auditLog,
    ) {}
    public function index(Request $request)
    {
        $maxDate = DB::table('ra_t_occ_cdr_detail')->max('start_date') ?: now()->toDateString();
        $startDate = date('Y-m-d', strtotime($maxDate . ' -30 days'));

        $activeKeywords = DB::table('ra_t_occ_cdr_detail')
            ->where('call_type', 'VAS')
            ->where(function ($q) {
                $q->whereNull('datasource')
                  ->orWhereNotIn('datasource', ['OCC_AGG', 'DB_OCC_AGG']);
            })
            ->where('keyword', '!=', 'Agrégat')
            ->whereNotNull('keyword')
            ->select('keyword')
            ->distinct()
            ->pluck('keyword');

        $activeKeywords = $activeKeywords
            ->merge(
                DB::table('ra_t_occ_agg')
                    ->where('call_type', 'VAS')
                    ->where('keyword', '!=', 'Agrégat')
                    ->whereNotNull('keyword')
                    ->select('keyword')
                    ->distinct()
                    ->pluck('keyword')
            )
            ->map(fn ($keyword) => strtoupper(trim((string) $keyword)))
            ->filter()
            ->unique()
            ->values();

        $existingKeywords = DB::table('ra_t_services')
            ->whereNotNull('keyword')
            ->pluck('keyword')
            ->map(fn ($keyword) => strtoupper(trim((string) $keyword)))
            ->filter()
            ->unique();

        $missingKeywords = $activeKeywords->diff($existingKeywords)->values();

        if ($missingKeywords->isNotEmpty()) {
            $now = now();
            $rows = $missingKeywords->map(function ($keyword) use ($now) {
                return [
                    'nom_fournisseur' => 'Autre',
                    'nom_service' => $keyword,
                    'numero_court' => '',
                    'keyword' => $keyword,
                    'type_service' => 'Service',
                    'prix' => 0,
                    'actif' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            DB::table('ra_t_services')->insert($rows);
        }

        $services = DB::table('ra_t_services as s')
            ->leftJoin('ra_t_alerts as a', function($join) {
                $join->on('a.keyword', '=', 's.keyword')
                     ->where('a.status', '=', false);
            })
            ->leftJoin(
                DB::raw("(
                    SELECT
                        keyword,
                        COUNT(*) as nb_cdr_total,
                        SUM(charge_amount) as revenus_total,
                        COUNT(DISTINCT a_msisdn) as nb_abonnes,
                        MAX(start_date) as derniere_activite
                    FROM ra_t_occ_cdr_detail
                    WHERE call_type = 'VAS'
                    GROUP BY keyword
                ) occ_stats"),
                'occ_stats.keyword',
                's.keyword'
            )
            ->select([
                's.*',
                
                // Stats CDR réelles (Total)
                DB::raw('COALESCE(occ_stats.nb_cdr_total, 0) as nb_cdr_30j'),
                DB::raw('COALESCE(occ_stats.revenus_total, 0) as revenus_30j'),
                DB::raw('COALESCE(occ_stats.nb_abonnes, 0) as nb_abonnes_30j'),
                DB::raw('occ_stats.derniere_activite'),
                
                // Données alertes agrégées
                DB::raw('COUNT(a.id) as nb_alertes_ouvertes'),
                DB::raw('COALESCE(SUM(a.count_nb_sms), 0) as total_sms_suspects'),
                DB::raw('MAX(a.seuil_pct) as seuil_max'),
                DB::raw('MIN(a.seuil_pct) as seuil_min'),
                DB::raw('MAX(a.start_date) as derniere_alerte'),
                DB::raw('STRING_AGG(DISTINCT a.motif, \', \') as motifs'),
                
                // Niveau urgence calculé
                DB::raw("
                    CASE
                        WHEN COUNT(a.id) = 0 THEN 'none'
                        WHEN MAX(a.seuil_pct) >= 50 THEN 'critique'
                        WHEN MAX(a.seuil_pct) >= 25 THEN 'haute'
                        WHEN MAX(a.seuil_pct) >= 10 THEN 'moyenne'
                        ELSE 'basse'
                    END as urgence_alerte
                "),
                
                // Ratio SMS suspects / total CDR
                DB::raw("
                    CASE
                        WHEN COALESCE(occ_stats.nb_cdr_total, 0) > 0
                        THEN ROUND(
                            COALESCE(SUM(a.count_nb_sms), 0)::numeric
                            / occ_stats.nb_cdr_total * 100, 2
                        )
                        ELSE 0
                    END as ratio_suspects_pct
                "),
            ])
            ->groupBy(
                's.id', 's.nom_service', 's.nom_fournisseur',
                's.keyword', 's.numero_court', 's.type_service',
                's.prix', 's.actif', 's.created_at', 's.updated_at',
                'occ_stats.nb_cdr_total', 'occ_stats.revenus_total',
                'occ_stats.nb_abonnes', 'occ_stats.derniere_activite'
            )
            ->orderByRaw("
                CASE
                    WHEN COUNT(a.id) > 0 THEN 0
                    ELSE 1
                END,
                revenus_30j DESC
            ")
            ->get();

        $services = $services->map(function ($service) {
            if (stripos($service->nom_service, 'inconnu') !== false) {
                $service->nom_service = 'Autre';
            }
            if (stripos($service->nom_fournisseur, 'inconnu') !== false) {
                $service->nom_fournisseur = 'Autre';
            }

            $storedPrice = (float) ($service->prix ?? 0);
            $nbCdr = (int) ($service->nb_cdr_30j ?? 0);
            $revenus = (float) ($service->revenus_30j ?? 0);

            $prixAffiche = $storedPrice > 0
                ? round($storedPrice, 3)
                : ($nbCdr > 0 && $revenus > 0 ? round($revenus / $nbCdr, 3) : null);

            $service->prix_affiche = $prixAffiche;

            return $service;
        });

        return response()->json($services);
    }

    public function store(Request $request)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'service_create',
                'systeme',
                null,
                0,
                ['page' => 'Services VAS', 'triggered_by' => 'user', 'keyword' => $request->keyword]
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for service_create', ['error' => $e->getMessage()]);
        }

        $validated = $request->validate([
            'nom_fournisseur' => 'required|string|max:100',
            'nom_service'     => 'required|string|max:100',
            'numero_court'    => 'required|string|max:20',
            'keyword'         => 'required|string|max:20',
            'type_service'    => 'nullable|in:Service,jeu',
            'prix'            => 'required|numeric|min:0',
            'actif'           => 'sometimes|boolean',
        ]);

        $validated['keyword'] = strtoupper($validated['keyword']);

        $id = DB::table('ra_t_services')->insertGetId([
            'nom_fournisseur' => $validated['nom_fournisseur'],
            'nom_service'     => $validated['nom_service'],
            'numero_court'    => $validated['numero_court'],
            'keyword'         => $validated['keyword'],
            'type_service'    => $validated['type_service'] ?? null,
            'prix'            => $validated['prix'],
            'actif'           => $request->boolean('actif', true),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $service = DB::table('ra_t_services')->find($id);
        
        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'service_id' => $service->id,
                    'keyword' => $service->keyword,
                    'nom' => $service->nom_service,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for service_create', ['error' => $e->getMessage()]);
        }

        $this->auditLog->log('create', 'service', "Service {$service->nom_service} créé (KW: {$service->keyword})", [], (array)$service, 'succes', $service->id);

        return response()->json($service, 201);
    }

    public function show($id)
    {
        $row = DB::table('ra_t_services')->find($id);
        if (! $row) {
            return response()->json(['message' => 'Service introuvable'], 404);
        }

        return response()->json($row);
    }

    public function update(Request $request, $id)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'service_update',
                'systeme',
                null,
                0,
                ['page' => 'Services VAS', 'service_id' => $id]
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for service_update', ['error' => $e->getMessage()]);
        }

        $avant = (array) DB::table('ra_t_services')->find($id);
        if (!$avant) {
             return response()->json(['message' => 'Service introuvable'], 404);
        }

        $validated = $request->validate([
            'nom_fournisseur' => 'sometimes|required|string|max:100',
            'nom_service'     => 'sometimes|required|string|max:100',
            'numero_court'    => 'sometimes|required|string|max:20',
            'keyword'         => 'sometimes|required|string|max:20',
            'type_service'    => 'nullable|in:Service,jeu',
            'prix'            => 'sometimes|required|numeric|min:0',
            'actif'           => 'sometimes|boolean',
        ]);

        $payload = array_merge(
            array_intersect_key($validated, array_flip([
                'nom_fournisseur', 'nom_service', 'numero_court', 'keyword', 'type_service', 'prix',
            ])),
            ['updated_at' => now()]
        );

        if ($request->has('actif')) {
            $payload['actif'] = $request->boolean('actif');
        }

        if (isset($payload['keyword'])) {
            $payload['keyword'] = strtoupper($payload['keyword']);
        }

        if (count($payload) > 1) {
            DB::table('ra_t_services')->where('id', $id)->update($payload);
        }

        $service = DB::table('ra_t_services')->find($id);
        $this->auditLog->log('update', 'service', "Service {$service->nom_service} modifié", $avant, (array)$service, 'succes', $id);
        
        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'service_id' => $id,
                    'champs_modifies' => array_keys($validated),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for service_update', ['error' => $e->getMessage()]);
        }

        return response()->json($service);
    }

    public function destroy($id)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'service_delete',
                'systeme',
                null,
                0,
                ['page' => 'Services VAS', 'service_id' => $id]
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for service_delete', ['error' => $e->getMessage()]);
        }

        $avant = (array) DB::table('ra_t_services')->find($id);
        $deleted = DB::table('ra_t_services')->where('id', $id)->delete();
        if (! $deleted) {
            try {
                if ($jobId) {
                    $this->monitor->failJob($jobId, 'Service introuvable');
                }
            } catch (\Exception $e) {
                Log::warning('EtlMonitorService failJob failed for service_delete', ['error' => $e->getMessage()]);
            }
            return response()->json(['message' => 'Service introuvable'], 404);
        }

        $this->auditLog->log('delete', 'service', "Service " . ($avant['nom_service'] ?? $id) . " supprimé", $avant ?? [], [], 'succes', $id);

        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success');
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for service_delete', ['error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Service supprimé']);
    }

    public function diagnosticAlertes($keyword)
    {
        $service = DB::table('ra_t_services')->where('keyword', $keyword)->first();
        if (!$service) {
            return response()->json(['message' => 'Service introuvable'], 404);
        }

        // 1. Historique 30j
        $stats = DB::table('ra_t_occ_cdr_detail')
            ->where('keyword', $keyword)
            ->where('call_type', 'VAS')
            ->where('start_date', '>=', now()->subDays(30)->toDateString())
            ->where('start_date', '<', now()->toDateString())
            ->selectRaw('start_date, COUNT(*) as cnt')
            ->groupBy('start_date')
            ->get();

        $moyenne = $stats->avg('cnt') ?? 0;
        $stdDev = 0;
        if ($stats->count() > 1) {
            $sumSq = 0;
            foreach ($stats as $s) {
                $sumSq += pow($s->cnt - $moyenne, 2);
            }
            $stdDev = sqrt($sumSq / ($stats->count() - 1));
        }
        $seuilNormal = $moyenne + 2 * $stdDev;

        // 2. Alertes actives
        $alerts = DB::table('ra_t_alerts')
            ->where('keyword', $keyword)
            ->where('status', false)
            ->get()
            ->map(function($a) use ($moyenne, $seuilNormal) {
                $reelCount = DB::table('ra_t_occ_cdr_detail')
                    ->where('keyword', $a->keyword)
                    ->where('call_type', 'VAS')
                    ->where('start_date', $a->start_date)
                    ->count();
                
                $seuilReel = $moyenne > 0 ? (($reelCount - $moyenne) / $moyenne * 100) : 0;
                
                return [
                    'id' => $a->id,
                    'date' => $a->start_date,
                    'count_nb_sms_alerte' => $a->count_nb_sms,
                    'count_nb_sms_reel' => $reelCount,
                    'ecart_calcul' => abs($a->count_nb_sms - $reelCount),
                    'seuil_pct_alerte' => $a->seuil_pct,
                    'seuil_pct_reel' => round($seuilReel, 2),
                    'ecart_seuil' => round(abs($a->seuil_pct - $seuilReel), 2),
                    'calcul_correct' => ($reelCount > $seuilNormal)
                ];
            });

        return response()->json([
            'keyword' => $keyword,
            'nom_service' => $service->nom_service,
            'historique_30j' => [
                'moyenne_journaliere' => round($moyenne, 2),
                'ecart_type' => round($stdDev, 2),
                'seuil_normal' => round($seuilNormal, 2),
                'pic_historique' => $stats->max('cnt') ?? 0,
                'jours_analyses' => $stats->count()
            ],
            'alertes_actives' => $alerts,
            'verification' => [
                'count_nb_sms_coherent' => $alerts->every(fn($a) => $a['ecart_calcul'] < 10),
                'seuil_pct_coherent' => $alerts->every(fn($a) => $a['ecart_seuil'] < 5),
                'methode_detection' => 'moyenne + 2 écarts-types (règle σ)',
                'anomalies_trouvees' => $alerts->filter(fn($a) => !$a['calcul_correct'])->count()
            ]
        ]);
    }

    public function mapping()
    {
        // Collect ALL keywords with CDR traffic (no call_type filter, matching revenue chart scope)
        $activeKeywords = DB::table('ra_t_occ_cdr_detail')
            ->whereNotNull('keyword')
            ->where('keyword', '!=', '')
            ->where('keyword', '!=', 'Agrégat')
            ->where(function ($q) {
                $q->whereNull('datasource')
                  ->orWhereNotIn('datasource', ['OCC_AGG', 'DB_OCC_AGG']);
            })
            ->select('keyword')
            ->distinct()
            ->pluck('keyword')
            ->merge(DB::table('ra_t_occ_agg')->whereNotNull('keyword')->where('keyword', '!=', '')->select('keyword')->distinct()->pluck('keyword'))
            ->map(fn ($keyword) => strtoupper(trim((string) $keyword)))
            ->filter()
            ->unique();

        $services = DB::table('ra_t_services')
            ->where('actif', true)
            ->orderBy('nom_service')
            ->get();

        // Build a set of nom_service values that have at least one keyword with traffic
        $servicesWithTraffic = $services
            ->filter(fn($s) => $activeKeywords->contains(strtoupper(trim($s->keyword))))
            ->pluck('nom_service')
            ->map(fn($n) => strtoupper(trim($n)))
            ->unique();

        $result = $services->map(function($s) use ($activeKeywords, $servicesWithTraffic) {
            $nomService = stripos($s->nom_service, 'inconnu') !== false ? 'Autre' : $s->nom_service;
            $nomFournisseur = stripos($s->nom_fournisseur, 'inconnu') !== false ? 'Autre' : $s->nom_fournisseur;

            return [
                'keyword'         => $s->keyword,
                'nom_service'     => $nomService,
                'nom_fournisseur' => $nomFournisseur,
                'nom_complet'     => $nomService . ' (' . $s->keyword . ')',
                'label'           => $nomService . ' — ' . $nomFournisseur,
                'has_traffic'     => $activeKeywords->contains(strtoupper(trim($s->keyword)))
                                     || $servicesWithTraffic->contains(strtoupper(trim($s->nom_service))),
            ];
        })->values();

        return response()->json($result);
    }
}
