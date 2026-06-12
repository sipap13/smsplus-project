<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EtlJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EtlMonitorController extends Controller
{
    public function byTypes(Request $request): JsonResponse
    {
        $request->validate([
            'types' => 'required|array',
            'types.*' => 'string',
            'limit' => 'integer|min:1|max:50',
            'include_running' => 'boolean',
            'page' => 'nullable|string',
        ]);

        $types = $request->types;
        $limit = $request->limit ?? 5;
        $includeRunning = $request->include_running ?? true;
        $page = $request->page;

        $query = EtlJob::whereIn('job_name', $types);

        // Filter by page if provided
        if ($page) {
            $query->where('page', $page);
        }

        if ($includeRunning) {
            $query->orderByRaw("CASE WHEN status = 'running' THEN 0 ELSE 1 END");
        }

        $jobs = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'job_name' => $job->job_name,
                    'job_type' => $job->job_type,
                    'status' => $job->status,
                    'duration_ms' => $job->duration_ms,
                    'pourcentage' => $job->pourcentage,
                    'lignes_traitees' => (int)($job->rows_processed ?? 0),
                    'lignes_inserees' => (int)($job->rows_processed ?? 0),
                    'lignes_ignorees' => (int)($job->rows_skipped ?? 0),
                    'total_lignes' => (int)(($job->metadata['total_rows'] ?? $job->metadata['nb_lignes'] ?? 0)),
                    'main_metric' => $this->getMainMetric($job),
                    'error_message' => $job->error_message,
                    'metadata' => $job->metadata 
                                   ? (is_string($job->metadata) ? json_decode($job->metadata, true) : $job->metadata)
                                   : [],
                    'finished_at' => $job->finished_at,
                    'started_at' => $job->started_at,
                    'is_running' => $job->status === 'running',
                    'page' => $job->page ?? 'Système',
                ];
            });

        return response()->json([
            'success' => true,
            'jobs' => $jobs,
            'meta' => [
                'total_requested' => count($types),
                'returned' => $jobs->count(),
                'include_running' => $includeRunning,
                'page_filter' => $page,
            ],
        ]);
    }

    public function triggerAgg(Request $request): JsonResponse
    {
        $source = $request->input('source', 'all');
        
        // On lance la commande en arrière-plan via le bus de tâches
        \Illuminate\Support\Facades\Artisan::queue('etl:agg-from-raw', [
            'source' => $source
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Agrégation des données CDR lancée en arrière-plan.',
        ]);
    }

    public function performanceStats(Request $request): JsonResponse
    {
        try {
            $days = (int) $request->query('days', 30);
            $dateLimit = now()->subDays($days);

            // Pour éviter de surcharger le frontend avec des milliers de points (ex: polling),
            // on agrège les données par heure directement en SQL.
            $jobs = \App\Models\EtlJob::where('status', 'success')
                ->whereNotNull('started_at')
                ->where('started_at', '>=', $dateLimit)
                ->where('started_at', '<=', now())
                ->selectRaw('
                    job_name, 
                    date_trunc(\'hour\', started_at) as hour, 
                    AVG(duration_ms) as avg_duration, 
                    SUM(COALESCE(CAST(metadata->>\'total_rows\' AS INTEGER), 0) + COALESCE(rows_processed, 0)) as volume_total,
                    COUNT(*) as job_count
                ')
                ->groupBy('job_name', 'hour')
                ->orderBy('hour', 'desc') // On prend les plus récents en premier
                ->limit(500) // Sécurité
                ->get();

            $result = [];
            $nowHour = now()->startOfHour();

            // On regroupe par job
            foreach ($jobs->reverse() as $job) {
                $name = $job->job_name;
                if (!isset($result[$name])) {
                    $result[$name] = [];
                }
                
                $result[$name][] = [
                    'date' => \Carbon\Carbon::parse($job->hour)->toIso8601String(),
                    'duration_sec' => round($job->avg_duration / 1000, 2),
                    'rows' => (int) $job->volume_total,
                    'count' => $job->job_count
                ];
            }

            // Pour chaque job, si la dernière heure n'est pas "maintenant", on ajoute un point à 0 
            // pour forcer le graphique à s'étendre jusqu'à l'heure actuelle.
            foreach ($result as $name => &$dataPoints) {
                if (empty($dataPoints)) continue;
                
                $lastPoint = end($dataPoints);
                $lastDate = \Carbon\Carbon::parse($lastPoint['date']);
                
                if ($lastDate->lt($nowHour)) {
                    $dataPoints[] = [
                        'date' => $nowHour->toIso8601String(),
                        'duration_sec' => 0,
                        'rows' => 0,
                        'count' => 0,
                        'is_placeholder' => true
                    ];
                }
            }

            return response()->json($result);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PerformanceStats Error', ['msg' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function lineageStats(): JsonResponse
    {
        try {
            $stats = [
                'ra_t_tmp_occ'       => \Illuminate\Support\Facades\DB::table('ra_t_tmp_occ')->count(),
                'ra_t_tmp_mmg'       => \Illuminate\Support\Facades\DB::table('ra_t_tmp_mmg')->count(),
                'ra_t_occ_cdr_detail'=> \Illuminate\Support\Facades\DB::table('ra_t_occ_cdr_detail')->count(),
                'ra_t_mmg_cdr_det'   => \Illuminate\Support\Facades\DB::table('ra_t_mmg_cdr_det')->count(),

                'ra_t_mmg_agg'       => \Illuminate\Support\Facades\DB::table('ra_t_mmg_agg')->count(),
                'ra_t_occ_agg'       => \Illuminate\Support\Facades\DB::table('ra_t_occ_agg')->count(),
                'ra_t_alerts'        => \Illuminate\Support\Facades\DB::table('ra_t_alerts')->count(),
                'ra_t_services'      => \Illuminate\Support\Facades\DB::table('ra_t_services')->count(),
                'ra_t_etl_jobs'      => \Illuminate\Support\Facades\DB::table('ra_t_etl_jobs')->count(),
            ];
            return response()->json($stats);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function stats(): JsonResponse
    {
        try {
            $today = now()->startOfDay();

            $stats = [
                'today' => [
                    'total'   => EtlJob::where('created_at', '>=', $today)->count(),
                    'success' => EtlJob::where('created_at', '>=', $today)->success()->count(),
                    'failed'  => EtlJob::where('created_at', '>=', $today)->failed()->count(),
                    'running' => EtlJob::running()->count(),
                ],
                'by_category' => EtlJob::where('created_at', '>=', $today)
                    ->selectRaw('job_name as job_type, status, COUNT(*) as count')
                    ->groupBy('job_type', 'status')
                    ->get()
                    ->groupBy('job_type')
                    ->map(function ($categoryGroup) {
                        return [
                            'total'   => $categoryGroup->sum('count'),
                            'success' => $categoryGroup->where('status', 'success')->sum('count'),
                            'failed'  => $categoryGroup->where('status', 'failed')->sum('count'),
                            'running' => $categoryGroup->where('status', 'running')->sum('count'),
                        ];
                    }),
                'recent_jobs' => EtlJob::orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($job) {
                        return [
                            'id'            => $job->id,
                            'job_name'      => $job->job_name,
                            'job_type'      => $job->job_name,
                            'status'        => $job->status,
                            'main_metric'   => $this->getMainMetric($job),
                            'relative_time' => $this->getRelativeTime($job->finished_at ?? $job->started_at),
                        ];
                    }),
            ];

            return response()->json([
                'success' => true,
                'data'    => $stats,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('EtlStats Error', ['msg' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'data'    => [
                    'today'        => ['total' => 0, 'success' => 0, 'failed' => 0, 'running' => 0],
                    'by_category'  => [],
                    'recent_jobs'  => [],
                ],
                'error' => $e->getMessage(),
            ], 200); // Return 200 with empty data instead of 500
        }
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
            'status' => 'in:running,success,failed,pending',
            'category' => 'string',
            'job_name' => 'string',
        ]);

        $query = EtlJob::orderBy('created_at', 'desc');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->category) {
            $query->where('job_type', $request->category);
        }

        if ($request->job_name) {
            $query->where('job_name', 'like', '%' . $request->job_name . '%');
        }

        $limit = $request->limit ?? 20;
        $jobs = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $jobs->items(),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
                'from' => $jobs->firstItem(),
                'to' => $jobs->lastItem(),
            ],
        ]);
    }

    private function getMainMetric(EtlJob $job): string
    {
        $metadata = $job->metadata ?? [];
        
        return match ($job->job_name) {
            'import_occ_csv', 'import_occ_xlsx', 'import_mmg_csv', 'import_mmg_xlsx' => 
                sprintf("%d lignes insérées", $metadata['processed_rows'] ?? $job->rows_processed ?? 0),
            
            'cdr_occ_paginate', 'cdr_mmg_paginate' => 
                sprintf("%d résultats", $metadata['processed_rows'] ?? $job->rows_processed ?? 0),
            
            'export_occ_excel', 'export_mmg_excel', 'export_revenus_csv' => 
                sprintf("%d lignes exportées", $metadata['processed_rows'] ?? $job->rows_processed ?? 0),
            
            'services_list_load' => 
                sprintf("%d services", $metadata['processed_rows'] ?? $job->rows_processed ?? 0),
            
            'user_login', 'user_2fa_verify' => 
                sprintf("Connexion %s", $job->status === 'success' ? 'réussie' : 'échouée'),
            
            'alerte_create', 'alerte_update' => 
                sprintf("Alerte %s", $job->status === 'success' ? 'traitée' : 'échouée'),
            
            'notifications_load' => 
                sprintf("%d notifications", $metadata['processed_rows'] ?? $job->rows_processed ?? 0),
            
            'msisdn_search_all', 'msisdn_timeline_build' => 
                sprintf("%d résultats", ($metadata['occ_total'] ?? 0) + ($metadata['mmg_total'] ?? 0) ?: ($job->rows_processed ?? 0)),
            
            default => 
                sprintf("%d lignes traitées", $job->rows_processed ?? 0),
        };
    }

    private function formatDuration(?int $durationMs): string
    {
        if (!$durationMs || $durationMs < 1000) {
            return $durationMs ? "{$durationMs}ms" : '--';
        }

        $seconds = floor($durationMs / 1000);
        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);

        if ($hours > 0) {
            return sprintf("%dh %dm", $hours, $minutes % 60);
        } elseif ($minutes > 0) {
            return sprintf("%dm %ds", $minutes, $seconds % 60);
        } else {
            return sprintf("%ds", $seconds);
        }
    }

    private function getRelativeTime($datetime): string
    {
        if (!$datetime) {
            return '--';
        }

        $now = now();
        $diff = $now->diffInSeconds($datetime);

        if ($diff < 60) {
            return "il y a {$diff}s";
        } elseif ($diff < 3600) {
            return "il y a " . floor($diff / 60) . "min";
        } elseif ($diff < 86400) {
            return "il y a " . floor($diff / 3600) . "h";
        } else {
            return "il y a " . floor($diff / 86400) . "j";
        }
    }
}
