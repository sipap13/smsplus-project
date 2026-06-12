<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EtlMonitorService;
use App\Services\MsisdnRiskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CdrController extends Controller
{
    private const PER_PAGE_DEFAULT = 50;

    private const PER_PAGE_MAX = 50;

    private const MSISDN_RESULT_LIMIT = 2000;

    public function __construct(
        protected EtlMonitorService $monitor,
        protected MsisdnRiskService $riskService,
    ) {}

    /** Colonnes retournées (lecture seule, pas d’id lourd si non demandé — on garde id pour stabilité UI). */
    private const OCC_SELECT = [
        'id',
        'a_msisdn',
        'b_msisdn',
        'start_date',
        'start_hour',
        'call_type',
        'event_type',
        'subscriber_type',
        'roaming_type',
        'partner',
        'charge_amount',
        'keyword',
    ];

    private const MMG_SELECT = [
        'id',
        'a_msisdn',
        'b_msisdn',
        'start_date',
        'start_hour',
        'event_type',
        'call_type',
        'event_status',
        'subscriber_type',
        'service_type',
    ];

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function occFilteredQuery(Request $request)
    {
        $q = DB::table('ra_t_occ_cdr_detail')
            ->where(function ($query) {
                $query->where('datasource', '!=', 'OCC_AGG')
                      ->orWhereNull('datasource');
            });

        $startDate = trim((string) $request->query('start_date', ''));
        if ($startDate !== '') {
            $q->where('start_date', $startDate);
        }

        $keyword = trim((string) $request->query('keyword', ''));
        if ($keyword !== '') {
            $q->where('keyword', $keyword);
        }

        $subscriberType = trim((string) $request->query('subscriber_type', ''));
        if ($subscriberType !== '') {
            $q->where('subscriber_type', $subscriberType);
        }

        $partner = trim((string) $request->query('partner', ''));
        if ($partner !== '') {
            $like = '%'.addcslashes($partner, '%_\\').'%';
            $q->where('partner', 'LIKE', $like);
        }

        return $q;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function mmgFilteredQuery(Request $request)
    {
        $q = DB::table('ra_t_mmg_cdr_det as m')
            ->selectRaw('m.id, m.b_msisdn, m.start_date, m.start_hour, m.event_type, m.call_type, m.event_status, m.subscriber_type, m.service_type, 1 as cdr_count');

        $startDate = trim((string) $request->query('start_date', ''));
        if ($startDate !== '') {
            $q->where('m.start_date', $startDate);
        }

        $subscriberType = trim((string) $request->query('subscriber_type', ''));
        if ($subscriberType !== '') {
            $q->where('m.subscriber_type', $subscriberType);
        }

        $eventStatus = trim((string) $request->query('event_status', ''));
        if ($eventStatus !== '') {
            if (strcasecmp($eventStatus, 'Success') === 0) {
                $q->whereRaw('LOWER(TRIM(COALESCE(m.event_status, \'\'))) = ?', ['success']);
            } elseif (strcasecmp($eventStatus, 'Failed') === 0) {
                $q->where(function ($w) {
                    $w->whereRaw('LOWER(TRIM(COALESCE(m.event_status, \'\'))) = ?', ['failed'])
                        ->orWhereRaw('LOWER(TRIM(COALESCE(m.event_status, \'\'))) LIKE ?', ['%fail%']);
                });
            }
        }

        return $q;
    }

    public function occ(Request $request)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'cdr_occ_paginate',
                'systeme',
                null,
                0,
                ['page' => 'CDR OCC', 'triggered_by' => 'user', 'filters' => $request->only(['start_date','keyword','subscriber_type','partner'])]
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for cdr_occ_paginate', ['error' => $e->getMessage()]);
        }

        $perPage = (int) $request->query('per_page', self::PER_PAGE_DEFAULT);
        $perPage = max(1, min($perPage, self::PER_PAGE_MAX));
        $page = max(1, (int) $request->query('page', 1));

        $base = $this->occFilteredQuery($request);
        $activeFilters = count(array_filter($request->only(['start_date','keyword','subscriber_type','partner'])));

        if ($activeFilters === 0) {
            $total = Cache::remember('occ_total_count_v2', 3600, fn() => (clone $base)->count());
            $totalCharge = Cache::remember('occ_total_charge_v2', 3600, fn() => (float) (clone $base)->sum('charge_amount'));
        } else {
            $total = (clone $base)->count();
            $totalCharge = (float) (clone $base)->sum('charge_amount');
        }

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 0;
        if ($lastPage > 0 && $page > $lastPage) {
            $page = $lastPage;
        }

        $data = (clone $base)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get(self::OCC_SELECT);

        $activeFilters = count(array_filter($request->only(['start_date','keyword','subscriber_type','partner'])));
        
        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'nb_resultats' => $total,
                    'page_courante' => $page,
                    'filtres_actifs' => $activeFilters,
                    'total_charge' => $totalCharge,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for cdr_occ_paginate', ['error' => $e->getMessage()]);
        }

        if (filter_var((string) env('APP_DEBUG_OCC_LOG', ''), FILTER_VALIDATE_BOOLEAN)) {
            Log::info('[cdr:occ] totals', [
                'activeFilters' => $activeFilters,
                'start_date' => $request->query('start_date'),
                'keyword' => $request->query('keyword'),
                'subscriber_type' => $request->query('subscriber_type'),
                'partner' => $request->query('partner'),
                'total' => $total,
                'total_charge_amount' => $totalCharge,
                'per_page' => $perPage,
                'page' => $page,
                'last_page' => $lastPage,
                'sql' => $base->toSql(),
            ]);
        }

        $servicesMap = DB::table('ra_t_services')->pluck('nom_service', 'keyword')->toArray();
        $data->transform(function($row) use ($servicesMap) {
            $row->nom_service = $servicesMap[$row->keyword] ?? $row->keyword;
            $row->b_msisdn = $this->formatOutgoingBMsisdn((string) $row->b_msisdn, 'occ');
            return $row;
        });

        return response()->json([
            'data'                 => $data,
            'total'                => $total,
            'per_page'             => $perPage,
            'current_page'         => $page,
            'last_page'            => $lastPage,
            'total_charge_amount'  => $totalCharge,
        ]);
    }

    public function mmg(Request $request)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'cdr_mmg_paginate',
                'systeme',
                null,
                0,
                ['page' => 'CDR MMG', 'triggered_by' => 'user']
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for cdr_mmg_paginate', ['error' => $e->getMessage()]);
        }

        $perPage = (int) $request->query('per_page', self::PER_PAGE_DEFAULT);
        $perPage = max(1, min($perPage, self::PER_PAGE_MAX));
        $page = max(1, (int) $request->query('page', 1));

        $base = $this->mmgFilteredQuery($request);
        $activeFilters = count(array_filter($request->only(['start_date','subscriber_type','event_status'])));

        if ($activeFilters === 0) {
            $total = Cache::remember('mmg_total_count_v2', 3600, fn() => (clone $base)->count());
        } else {
            $total = (clone $base)->count();
        }

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 0;
        if ($lastPage > 0 && $page > $lastPage) {
            $page = $lastPage;
        }

        $data = (clone $base)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get(self::MMG_SELECT);

        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'nb_resultats' => $total,
                    'page_courante' => $page,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for cdr_mmg_paginate', ['error' => $e->getMessage()]);
        }

        $servicesMap = DB::table('ra_t_services')->pluck('nom_service', 'keyword')->toArray();
        $data->transform(function($row) use ($servicesMap) {
            $row->nom_service = $servicesMap[$row->service_type] ?? $row->service_type;
            $row->b_msisdn = $this->formatOutgoingBMsisdn((string) $row->b_msisdn, 'mmg');
            $row->cdr_count = 1;
            return $row;
        });

        return response()->json([
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $lastPage,
        ]);
    }

    public function occFilterOptions()
    {
        $payload = Cache::remember('cdr_occ_filter_options_v1', 3600, function () {
            $keywords = DB::table('ra_t_services')
                ->whereNotNull('keyword')
                ->where('keyword', '!=', '')
                ->distinct()
                ->orderBy('keyword')
                ->pluck('keyword')
                ->values()
                ->all();

            $subscriberTypes = DB::table('ra_t_occ_cdr_detail')
                ->select('subscriber_type')
                ->whereNotNull('subscriber_type')
                ->where('subscriber_type', '!=', '')
                ->distinct()
                ->orderBy('subscriber_type')
                ->limit(200)
                ->pluck('subscriber_type')
                ->values()
                ->all();

            return [
                'keywords'          => $keywords,
                'subscriber_types' => $subscriberTypes,
            ];
        });

        return response()->json($payload);
    }

    public function mmgFilterOptions()
    {
        $payload = Cache::remember('cdr_mmg_filter_options_v1', 3600, function () {
            $subscriberTypes = DB::table('ra_t_mmg_cdr_det')
                ->select('subscriber_type')
                ->whereNotNull('subscriber_type')
                ->where('subscriber_type', '!=', '')
                ->distinct()
                ->orderBy('subscriber_type')
                ->limit(200)
                ->pluck('subscriber_type')
                ->values()
                ->all();

            return [
                'subscriber_types' => $subscriberTypes,
                'event_statuses'     => ['Success', 'Failed'],
            ];
        });

        return response()->json($payload);
    }

    public function byMsisdn(string $msisdn)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'msisdn_search_all',
                'systeme',
                null,
                0,
                ['page' => 'Recherche MSISDN', 'msisdn' => $msisdn]
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for msisdn_search_all', ['error' => $e->getMessage()]);
        }

        $norm = $this->normalizeMsisdn($msisdn);
        if (strlen($norm) < 5 || strlen($norm) > 32) {
            try {
                if ($jobId) {
                    $this->monitor->failJob($jobId, 'MSISDN invalide');
                }
            } catch (\Exception $e) {
                Log::warning('EtlMonitorService failJob failed for msisdn_search_all', ['error' => $e->getMessage()]);
            }
            return response()->json(['message' => 'MSISDN invalide'], 422);
        }

        $limit = self::MSISDN_RESULT_LIMIT;

        $occBase = DB::table('ra_t_occ_cdr_detail')->where(function ($w) use ($norm) {
            $w->where('a_msisdn', $norm)->orWhere('b_msisdn', $norm);
        })->where(function ($query) {
            $query->where('datasource', '!=', 'OCC_AGG')
                  ->orWhereNull('datasource');
        });
        $occTotal = (clone $occBase)->count();
        $occ = (clone $occBase)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(self::OCC_SELECT);

        $mmgBase = DB::table('ra_t_mmg_cdr_det')->where(function ($w) use ($norm) {
            $w->where('a_msisdn', $norm)->orWhere('b_msisdn', $norm);
        });
        $mmgTotal = (clone $mmgBase)->count();
        $mmg = (clone $mmgBase)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(self::MMG_SELECT);

        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'msisdn' => $norm,
                    'occ_total' => $occTotal,
                    'mmg_total' => $mmgTotal,
                    'occ_shown' => $occ->count(),
                    'mmg_shown' => $mmg->count(),
                    'processed_rows' => $occTotal + $mmgTotal,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for msisdn_search_all', ['error' => $e->getMessage()]);
        }

        $servicesMap = DB::table('ra_t_services')->pluck('nom_service', 'keyword')->toArray();
        
        $occ->transform(function($row) use ($servicesMap) {
            $row->nom_service = $servicesMap[$row->keyword] ?? $row->keyword;
            $row->b_msisdn = $this->formatOutgoingBMsisdn((string) $row->b_msisdn, 'occ');
            return $row;
        });
        
        $mmg->transform(function($row) use ($servicesMap) {
            $row->nom_service = $servicesMap[$row->service_type] ?? $row->service_type;
            $row->b_msisdn = $this->formatOutgoingBMsisdn((string) $row->b_msisdn, 'mmg');
            return $row;
        });

        return response()->json([
            'occ'              => $occ,
            'mmg'              => $mmg,
            'occ_total'        => $occTotal,
            'mmg_total'        => $mmgTotal,
            'occ_shown'        => $occ->count(),
            'mmg_shown'        => $mmg->count(),
            'occ_truncated'    => $occTotal > $occ->count(),
            'mmg_truncated'    => $mmgTotal > $mmg->count(),
            'msisdn_normalized'=> $norm,
            'risk_analysis'    => $this->safeRiskAnalysis($norm),
        ]);
    }

    public function timeline(string $msisdn, Request $request)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'msisdn_timeline_build',
                'systeme',
                null,
                0,
                ['page' => 'Recherche MSISDN', 'msisdn' => $msisdn]
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for msisdn_timeline_build', ['error' => $e->getMessage()]);
        }

        $norm = $this->normalizeMsisdn($msisdn);
        if (strlen($norm) < 5 || strlen($norm) > 32) {
            try {
                if ($jobId) {
                    $this->monitor->failJob($jobId, 'MSISDN invalide');
                }
            } catch (\Exception $e) {
                Log::warning('EtlMonitorService failJob failed for msisdn_timeline_build', ['error' => $e->getMessage()]);
            }
            return response()->json(['message' => 'MSISDN invalide'], 422);
        }

        // Déterminer la date de fin : la plus récente parmi TOUTES les transactions du MSISDN
        // (en tant qu'appelant a_msisdn OU destinataire b_msisdn, dans OCC et MMG)
        // On force le cast ::date pour normaliser les timestamps et ::text pour comparaison PHP fiable
        $latestOcc = DB::table('ra_t_occ_cdr_detail')
            ->where(function ($w) use ($norm) {
                $w->where('a_msisdn', $norm)->orWhere('b_msisdn', $norm);
            })
            ->where(function ($query) {
                $query->where('datasource', '!=', 'OCC_AGG')
                      ->orWhereNull('datasource');
            })
            ->selectRaw("MAX(start_date::date)::text as max_date")
            ->value('max_date');

        $latestMmg = DB::table('ra_t_mmg_cdr_det')
            ->where(function ($w) use ($norm) {
                $w->where('a_msisdn', $norm)->orWhere('b_msisdn', $norm);
            })
            ->selectRaw("MAX(start_date::date)::text as max_date")
            ->value('max_date');

        $candidates = array_filter([$latestOcc, $latestMmg]);
        $latestRecord = !empty($candidates) ? max($candidates) : now()->format('Y-m-d');

        Log::info("[Timeline] MSISDN {$norm} — latestOcc={$latestOcc} latestMmg={$latestMmg} → dateFin={$latestRecord}");

        $dateFin   = $request->query('date_fin')
            ? \Carbon\Carbon::parse($request->query('date_fin'))->format('Y-m-d')
            : $latestRecord;

        $earliestOcc = DB::table('ra_t_occ_cdr_detail')
            ->where(function ($w) use ($norm) {
                $w->where('a_msisdn', $norm)->orWhere('b_msisdn', $norm);
            })
            ->where(function ($query) {
                $query->where('datasource', '!=', 'OCC_AGG')
                      ->orWhereNull('datasource');
            })
            ->selectRaw("MIN(start_date::date)::text as min_date")
            ->value('min_date');

        $earliestMmg = DB::table('ra_t_mmg_cdr_det')
            ->where(function ($w) use ($norm) {
                $w->where('a_msisdn', $norm)->orWhere('b_msisdn', $norm);
            })
            ->selectRaw("MIN(start_date::date)::text as min_date")
            ->value('min_date');

        $minCandidates = array_filter([$earliestOcc, $earliestMmg]);
        $earliestRecord = !empty($minCandidates) ? min($minCandidates) : \Carbon\Carbon::parse($dateFin)->subDays(60)->format('Y-m-d');

        $dateDebut = $request->query('date_debut')
            ? \Carbon\Carbon::parse($request->query('date_debut'))->format('Y-m-d')
            : $earliestRecord;

        Log::info("[Timeline] Fenêtre: {$dateDebut} → {$dateFin}");

        $source = in_array($request->query('source'), ['occ', 'mmg', 'all']) ? $request->query('source') : 'all';

        $services = DB::table('ra_t_services')
            ->whereNotNull('keyword')
            ->where('keyword', '!=', '')
            ->pluck('nom_service', 'keyword')
            ->toArray();

        $items = [];

        if ($source === 'occ' || $source === 'all') {
            $occRows = DB::table('ra_t_occ_cdr_detail')
                ->where(function ($w) use ($norm) {
                    $w->where('a_msisdn', $norm)->orWhere('b_msisdn', $norm);
                })
                ->where(function ($query) {
                    $query->where('datasource', '!=', 'OCC_AGG')
                          ->orWhereNull('datasource');
                })
                ->whereBetween('start_date', [$dateDebut, $dateFin])
                ->orderByDesc('start_date')
                ->orderByDesc('start_hour')
                ->limit(200)
                ->get([
                    'id',
                    'a_msisdn',
                    'start_date',
                    'start_hour',
                    'orig_start_time',
                    'b_msisdn',
                    'keyword',
                    'charge_amount',
                    'event_type',
                    'subscriber_type',
                    'call_type',
                ]);

            foreach ($occRows as $row) {
                $dt   = $this->buildDatetime($row->start_date, $row->start_hour, $row->orig_start_time);
                $role = ($row->a_msisdn === $norm) ? 'appelant' : 'destinataire';
                $items[] = [
                    'id'              => (int) $row->id,
                    'source'          => 'OCC',
                    'role'            => $role,
                    'date'            => $row->start_date,
                    'heure'           => (int) $row->start_hour,
                    'datetime'        => $dt,
                    'service'         => $row->keyword,
                    'nom_service'     => $services[$row->keyword] ?? strtoupper($row->keyword ?? ''),
                    'montant'         => (float) $row->charge_amount,
                    'destinataire'    => $this->formatOutgoingBMsisdn((string) $row->b_msisdn, 'occ'),
                    'statut'          => 'Success',
                    'type'            => $row->event_type ?? $row->call_type ?? 'VAS',
                    'subscriber_type' => $row->subscriber_type,
                    'raw'             => $row,
                ];
            }
        }

        if ($source === 'mmg' || $source === 'all') {
            $mmgRows = DB::table('ra_t_mmg_cdr_det')
                ->where(function ($w) use ($norm) {
                    $w->where('a_msisdn', $norm)->orWhere('b_msisdn', $norm);
                })
                ->whereBetween('start_date', [$dateDebut, $dateFin])
                ->orderByDesc('start_date')
                ->orderByDesc('start_hour')
                ->limit(200)
                ->get([
                    'id',
                    'a_msisdn',
                    'start_date',
                    'start_hour',
                    'orig_start_time',
                    'b_msisdn',
                    'service_type',
                    'event_type',
                    'subscriber_type',
                    'event_status',
                    'call_type',
                ]);

            foreach ($mmgRows as $row) {
                $dt   = $this->buildDatetime($row->start_date, $row->start_hour, $row->orig_start_time);
                $role = ($row->a_msisdn === $norm) ? 'appelant' : 'destinataire';
                $items[] = [
                    'id'              => (int) $row->id,
                    'source'          => 'MMG',
                    'role'            => $role,
                    'date'            => $row->start_date,
                    'heure'           => (int) $row->start_hour,
                    'datetime'        => $dt,
                    'service'         => $row->service_type,
                    'nom_service'     => $services[$row->service_type] ?? strtoupper($row->service_type ?? ''),
                    'montant'         => 0.00,
                    'destinataire'    => $this->formatOutgoingBMsisdn((string) $row->b_msisdn, 'mmg'),
                    'statut'          => $row->event_status ?? '—',
                    'type'            => $row->event_type ?? $row->call_type ?? 'SMS',
                    'subscriber_type' => $row->subscriber_type,
                    'raw'             => $row,
                ];
            }
        }

        usort($items, function ($a, $b) {
            return strcmp($b['datetime'], $a['datetime']);
        });

        $items = $this->markDuplicates($items);

        // par_jour uses an aggregated query to keep the timeline complete.
        $rawParJour = [];

        if ($source === 'occ' || $source === 'all') {
            $occAgg = DB::table('ra_t_occ_cdr_detail')
                ->where(function ($w) use ($norm) {
                    $w->where('a_msisdn', $norm)->orWhere('b_msisdn', $norm);
                })
                ->where(function ($query) {
                    $query->where('datasource', '!=', 'OCC_AGG')
                          ->orWhereNull('datasource');
                })
                ->whereBetween('start_date', [$dateDebut, $dateFin])
                ->selectRaw("start_date::date as jour, COUNT(*) as nb, SUM(charge_amount) as montant")
                ->groupByRaw('start_date::date')
                ->get();

            foreach ($occAgg as $row) {
                $d = (string) $row->jour;
                $rawParJour[$d]['date']            = $d;
                $rawParJour[$d]['nb_transactions'] = ($rawParJour[$d]['nb_transactions'] ?? 0) + (int) $row->nb;
                $rawParJour[$d]['montant_total']   = ($rawParJour[$d]['montant_total'] ?? 0.0) + (float) $row->montant;
                $rawParJour[$d]['sources'][]       = 'OCC';
            }
        }

        if ($source === 'mmg' || $source === 'all') {
            $mmgAgg = DB::table('ra_t_mmg_cdr_det')
                ->where(function ($w) use ($norm) {
                    $w->where('a_msisdn', $norm)->orWhere('b_msisdn', $norm);
                })
                ->whereBetween('start_date', [$dateDebut, $dateFin])
                ->selectRaw("start_date::date as jour, COUNT(*) as nb")
                ->groupByRaw('start_date::date')
                ->get();

            foreach ($mmgAgg as $row) {
                $d = (string) $row->jour;
                $rawParJour[$d]['date']            = $d;
                $rawParJour[$d]['nb_transactions'] = ($rawParJour[$d]['nb_transactions'] ?? 0) + (int) $row->nb;
                $rawParJour[$d]['montant_total']   = $rawParJour[$d]['montant_total'] ?? 0.0;
                $rawParJour[$d]['sources'][]       = 'MMG';
            }
        }

        // Déduplication des sources + tri
        foreach ($rawParJour as $d => &$entry) {
            $entry['sources'] = array_values(array_unique($entry['sources'] ?? []));
        }
        unset($entry);

        $parJour = array_values($rawParJour);
        usort($parJour, fn ($a, $b) => strcmp($b['date'], $a['date']));

        // Calculs agrégés depuis les vrais totaux (pas depuis $items limité)
        $totalRevenus     = array_sum(array_column($rawParJour, 'montant_total'));
        $servicesUtilises = [];
        $datesUniques     = [];

        foreach ($items as $it) {
            if ($it['service'] && !in_array($it['service'], $servicesUtilises)) {
                $servicesUtilises[] = $it['service'];
            }
        }
        foreach ($rawParJour as $d => $_) {
            $datesUniques[$d] = true;
        }


        $sortedDates = array_keys($datesUniques);
        sort($sortedDates);
        $premierContact = $sortedDates[0] ?? null;
        $dernierContact = $sortedDates[count($sortedDates) - 1] ?? null;

        $gaps = [];
        for ($i = 1; $i < count($sortedDates); $i++) {
            $diff = (new \Carbon\Carbon($sortedDates[$i]))->diffInDays(new \Carbon\Carbon($sortedDates[$i - 1]));
            if ($diff > 3) {
                $gaps[] = [
                    'apres'  => $sortedDates[$i - 1],
                    'avant'  => $sortedDates[$i],
                    'jours'  => $diff - 1,
                ];
            }
        }

        $totalTransactions = array_sum(array_column($rawParJour, 'nb_transactions'));

        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'msisdn'              => $norm,
                    'total_transactions'  => $totalTransactions,
                    'periode_jours'       => count($sortedDates),
                    'services_utilises'   => count($servicesUtilises),
                    'processed_rows'      => $totalTransactions,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for msisdn_timeline_build', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'msisdn'             => $norm,
            'periode'            => ['debut' => $dateDebut, 'fin' => $dateFin],
            'total_transactions' => $totalTransactions,     // ← vrai total (sans limite)
            'timeline_shown'     => count($items),          // ← nb d'éléments détaillés (≤200)
            'total_revenus'      => round($totalRevenus, 3),
            'timeline'           => $items,
            'par_jour'           => $parJour,               // ← toujours complet (requête agrégée)
            'services_utilises'  => $servicesUtilises,
            'premier_contact'    => $premierContact,
            'dernier_contact'    => $dernierContact,
            'gaps'               => $gaps,
        ]);
    }

    private function buildDatetime(string $date, ?int $hour, ?string $orig): string
    {
        if ($orig && preg_match('/(\d{4})[-\/](\d{2})[-\/](\d{2}).*(\d{2}):(\d{2}):(\d{2})/', $orig, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}T{$m[4]}:{$m[5]}:{$m[6]}";
        }
        $h = str_pad((string) ($hour ?? 0), 2, '0', STR_PAD_LEFT);
        return "{$date}T{$h}:00:00";
    }

    private function markDuplicates(array $items): array
    {
        $seen = [];
        foreach ($items as $i => $it) {
            $key = implode('|', [
                $it['date'],
                $it['heure'],
                $it['source'],
                $it['service'] ?? '',
                $it['montant'],
                $it['destinataire'] ?? '',
            ]);
            $items[$i]['doublon'] = isset($seen[$key]);
            $seen[$key] = true;
        }
        return $items;
    }

    private function normalizeMsisdn(string $raw): string
    {
        $s = preg_replace('/\s+/', '', $raw) ?? '';
        $s = ltrim($s, '+');

        return $s;
    }

    private function formatOutgoingBMsisdn(string $raw, string $type): string
    {
        $s = trim($raw);
        $s = preg_replace('/\s+/', '', $s);
        $s = ltrim($s, '+');

        if ($s === '') {
            return $s;
        }

        // Preserve internet marker
        if (strcasecmp($s, 'internet.tn') === 0) {
            return 'internet.tn';
        }

        // If contains letters, return as-is
        if (preg_match('/[A-Za-z]/', $s)) {
            return $s;
        }

        // Numeric: ensure 216-prefixed full or short-code with 216 prefix
        if (ctype_digit($s)) {
            if (str_starts_with($s, '216')) {
                return $s;
            }

            // Short codes (<=6 digits) -> prefix with 216
            if (strlen($s) <= 6) {
                return '216' . ltrim($s, '0');
            }

            // Local 8-digit numbers (e.g., 20xxxxxx) -> prefix 216
            if (strlen($s) === 8) {
                return '216' . $s;
            }

            // Fallback: prefix 216
            return '216' . ltrim($s, '0');
        }

        return $s;
    }

    private function safeRiskAnalysis(string $msisdn): array
    {
        try {
            $result = $this->riskService->analyze($msisdn);
            $result['_ok'] = true;
            return $result;
        } catch (
            \Throwable $e
        ) {
            Log::warning('MsisdnRiskService failed for msisdn_search_all', [
                'msisdn' => $msisdn,
                'error' => $e->getMessage(),
            ]);

            return [
                'score' => null,
                'level' => null,
                'reasons' => [],
                '_ok' => false,
                '_error' => 'Le calcul du score de risque a échoué',
            ];
        }
    }
}
