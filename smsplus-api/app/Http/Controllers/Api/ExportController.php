<?php

namespace App\Http\Controllers\Api;

use App\Exports\AlertsExport;
use App\Exports\CdrMmgExport;
use App\Exports\CdrOccExport;
use App\Exports\ServicesExport;
use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Services\EtlMonitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected EtlMonitorService $monitor,
        protected \App\Services\AuditLogService $auditLog,
    ) {}

    /**
     * Export CDR OCC filtré en Excel
     */
    public function exportOcc(Request $request)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'export_occ_excel',
                'rapport',
                'CDR_OCC_' . date('Y-m-d') . '.xlsx',
                0,
                ['page' => 'CDR OCC', 'triggered_by' => 'user']
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for export_occ_excel', ['error' => $e->getMessage()]);
        }

        $startDate = $request->query('start_date');
        $keyword = $request->query('keyword');
        $subscriberType = $request->query('subscriber_type');
        $partner = $request->query('partner');

        $filename = 'CDR_OCC_'.now()->format('Y-m-d').'.xlsx';
        $this->notifyReportForRequester($request, now()->format('Y-m'), "CDR OCC");

        // Simuler le nombre de lignes exportées (à adapter selon votre logique)
        $count = 0;
        if ($startDate || $keyword || $subscriberType || $partner) {
            $base = DB::table('ra_t_occ_cdr_detail');
            if ($startDate) $base->where('start_date', $startDate);
            if ($keyword) $base->where('keyword', $keyword);
            if ($subscriberType) $base->where('subscriber_type', $subscriberType);
            if ($partner) $base->where('partner', 'LIKE', '%' . $partner . '%');
            $count = $base->count();
        }

        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'nb_lignes' => $count,
                    'nom_fichier' => $filename,
                    'filtres' => array_filter([
                        'start_date' => $startDate,
                        'keyword' => $keyword,
                        'subscriber_type' => $subscriberType,
                        'partner' => $partner,
                    ]),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for export_occ_excel', ['error' => $e->getMessage()]);
        }

        return Excel::download(
            new CdrOccExport($startDate, $keyword, $subscriberType, $partner),
            $filename
        );
    }

    /**
     * Export CDR MMG filtré en Excel
     */
    public function exportMmg(Request $request)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'export_mmg_excel',
                'rapport',
                'CDR_MMG_' . date('Y-m-d') . '.xlsx',
                0,
                ['page' => 'CDR MMG']
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for export_mmg_excel', ['error' => $e->getMessage()]);
        }

        $startDate = $request->query('start_date');
        $eventStatus = $request->query('event_status');
        $subscriberType = $request->query('subscriber_type');

        $filename = 'CDR_MMG_'.now()->format('Y-m-d').'.xlsx';
        $this->notifyReportForRequester($request, now()->format('Y-m'), "CDR MMG");

        // Simuler le nombre de lignes exportées
        $count = 0;
        if ($startDate || $eventStatus || $subscriberType) {
            $base = DB::table('ra_t_mmg_cdr_det');
            if ($startDate) $base->where('start_date', $startDate);
            if ($eventStatus) $base->where('event_status', $eventStatus);
            if ($subscriberType) $base->where('subscriber_type', $subscriberType);
            $count = $base->count();
        }

        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'nb_lignes' => $count,
                    'nom_fichier' => $filename,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for export_mmg_excel', ['error' => $e->getMessage()]);
        }

        return Excel::download(
            new CdrMmgExport($startDate, $eventStatus, $subscriberType),
            $filename
        );
    }

    /**
     * Export Services en Excel
     */
    public function exportServices(Request $request)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'export_services_excel',
                'rapport',
                null,
                0,
                ['page' => 'Services VAS']
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for export_services_excel', ['error' => $e->getMessage()]);
        }

        $filename = 'Services_'.now()->format('Y-m-d').'.xlsx';
        $this->notifyReportForRequester($request, now()->format('Y-m'), "Services");

        $count = DB::table('ra_t_services')->count();

        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'nb_services' => $count,
                    'nom_fichier' => $filename,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for export_services_excel', ['error' => $e->getMessage()]);
        }

        return Excel::download(
            new ServicesExport,
            $filename
        );
    }

    /**
     * Export Alertes en Excel
     */
    public function exportAlerts(Request $request)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'export_alertes_excel',
                'rapport',
                null,
                0,
                ['page' => 'Alertes fraude']
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for export_alertes_excel', ['error' => $e->getMessage()]);
        }

        $filename = 'Alertes_'.now()->format('Y-m-d').'.xlsx';
        $this->notifyReportForRequester($request, now()->format('Y-m'), "Alertes Fraude");

        $count = DB::table('ra_t_alerts')->count();

        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'nb_alertes' => $count,
                    'nom_fichier' => $filename,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for export_alertes_excel', ['error' => $e->getMessage()]);
        }

        return Excel::download(
            new AlertsExport,
            $filename
        );
    }

    public function revenusCsv(Request $request)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'export_revenus_csv',
                'rapport',
                null,
                0,
                ['page' => 'Revenus détaillés']
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for export_revenus_csv', ['error' => $e->getMessage()]);
        }

        $allowedCallTypes = ['VAS', 'SMS', 'VOICE'];
        $includeData = in_array(strtolower((string) $request->query('include_data', '0')), ['1', 'true', 'yes'], true);
        $days = max(1, min((int) $request->query('days', 30), 365));

        $q = DB::table('ra_t_occ_cdr_detail as o')
            ->leftJoin('ra_t_services as s', 's.keyword', '=', 'o.keyword')
            ->where('o.start_date', '>=', now()->subDays($days)->toDateString());

        if (! $includeData) {
            $q->whereIn('o.call_type', $allowedCallTypes);
        }

        $rows = $q
            ->selectRaw("o.start_date as date, COALESCE(NULLIF(s.nom_fournisseur,''), 'Inconnu') as fournisseur, COALESCE(NULLIF(s.nom_service,''), COALESCE(NULLIF(o.keyword,''), 'Autre')) as service, COALESCE(NULLIF(o.keyword,''), 'Autre') as keyword_label, SUM(o.charge_amount) as revenus_dt, COUNT(*) as nb_cdr")
            ->groupBy('date')
            ->groupBy('fournisseur')
            ->groupBy('service')
            ->groupBy('keyword_label')
            ->orderByDesc('date')
            ->limit(20000)
            ->get();

        $filename = 'revenus_export_'.now()->format('Ymd_His').'.csv';
        $this->notifyReportForRequester($request, now()->format('Y-m'), "Revenus détaillés");

        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'nb_lignes' => $rows->count(),
                    'periode_jours' => $days,
                    'nom_fichier' => $filename,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for export_revenus_csv', ['error' => $e->getMessage()]);
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for Excel
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['date', 'fournisseur', 'service', 'keyword', 'revenus_dt', 'nb_cdr'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->date,
                    $r->fournisseur,
                    $r->service,
                    $r->keyword_label,
                    (string) $r->revenus_dt,
                    (string) $r->nb_cdr,
                ], ';');
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function notifyReportForRequester(Request $request, string $month, string $type = 'Rapport'): void
    {
        $user = $request->attributes->get('auth_user');
        $userId = (int) ($user->id ?? 0);
        if ($userId <= 0) {
            return;
        }
        $this->notificationService->notifyReportReady($userId, $month);
        $this->auditLog->log('export', 'rapport', "Export $type : Période $month");
    }
}
