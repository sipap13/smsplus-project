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
                    'pourcentage' => $job->pourcentage ?? 0,
                    'lignes_traitees' => (int)($job->rows_processed ?? 0),
                    'lignes_inserees' => (int)($job->rows_inserted ?? 0),
                    'lignes_ignorees' => (int)($job->rows_skipped ?? 0),
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

    public function stats(): JsonResponse
    {
        $today = now()->startOfDay();
        
        $stats = [
            'today' => [
                'total' => EtlJob::where('created_at', '>=', $today)->count(),
                'success' => EtlJob::where('created_at', '>=', $today)->success()->count(),
                'failed' => EtlJob::where('created_at', '>=', $today)->failed()->count(),
                'running' => EtlJob::running()->count(),
            ],
            'by_category' => EtlJob::where('created_at', '>=', $today)
                ->selectRaw('job_type, status, COUNT(*) as count')
                ->groupBy('job_type', 'status')
                ->get()
                ->groupBy('job_type')
                ->map(function ($categoryGroup) {
                    return [
                        'total' => $categoryGroup->sum('count'),
                        'success' => $categoryGroup->where('status', 'success')->sum('count'),
                        'failed' => $categoryGroup->where('status', 'failed')->sum('count'),
                        'running' => $categoryGroup->where('status', 'running')->sum('count'),
                    ];
                }),
            'recent_jobs' => EtlJob::orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($job) {
                    return [
                        'id' => $job->id,
                        'job_name' => $job->job_name,
                        'job_type' => $job->job_type,
                        'status' => $job->status,
                        'main_metric' => $this->getMainMetric($job),
                        'relative_time' => $this->getRelativeTime($job->finished_at ?? $job->started_at),
                    ];
                }),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
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
                sprintf("%d lignes insérées", $metadata['rows_inserted'] ?? $job->rows_inserted ?? 0),
            
            'cdr_occ_paginate', 'cdr_mmg_paginate' => 
                sprintf("%d résultats", $metadata['nb_resultats'] ?? $job->rows_processed ?? 0),
            
            'export_occ_excel', 'export_mmg_excel', 'export_revenus_csv' => 
                sprintf("%d lignes exportées", $metadata['nb_lignes'] ?? $job->rows_processed ?? 0),
            
            'services_list_load' => 
                sprintf("%d services", $metadata['nb_services'] ?? $job->rows_processed ?? 0),
            
            'user_login', 'user_2fa_verify' => 
                sprintf("Connexion %s", $job->status === 'success' ? 'réussie' : 'échouée'),
            
            'alerte_create', 'alerte_update' => 
                sprintf("Alerte %s", $job->status === 'success' ? 'traitée' : 'échouée'),
            
            'notifications_load' => 
                sprintf("%d notifications", $metadata['nb_notifications'] ?? $job->rows_processed ?? 0),
            
            'msisdn_search_all', 'msisdn_timeline_build' => 
                sprintf("%d résultats", $metadata['occ_total'] + $metadata['mmg_total'] ?? $job->rows_processed ?? 0),
            
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
