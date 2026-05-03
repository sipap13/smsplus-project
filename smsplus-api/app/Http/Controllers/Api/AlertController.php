<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Services\EtlMonitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AlertController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected EtlMonitorService $monitor,
        protected \App\Services\AuditLogService $auditLog,
    ) {}

    public function index()
    {
        $alerts = DB::table('ra_t_alerts')->orderBy('start_date', 'desc')->orderBy('id', 'desc')->get();

        return response()->json($alerts);
    }

    public function store(Request $request)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'alerte_create',
                'systeme',
                null,
                0,
                ['page' => 'Alertes fraude', 'keyword' => $request->keyword]
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for alerte_create', ['error' => $e->getMessage()]);
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'nom_service' => 'nullable|string|max:100',
            'numero_court' => 'nullable|string|max:20',
            'keyword' => 'nullable|string|max:20',
            'nom_fournisseur' => 'nullable|string|max:100',
            'seuil_pct' => 'nullable|numeric|min:0|max:100',
            'count_nb_sms' => 'nullable|integer|min:0',
            'motif' => 'nullable|string|max:255',
        ]);

        $id = DB::table('ra_t_alerts')->insertGetId([
            'start_date' => $validated['start_date'],
            'nom_service' => $validated['nom_service'] ?? null,
            'numero_court' => $validated['numero_court'] ?? null,
            'keyword' => $validated['keyword'] ?? null,
            'nom_fournisseur' => $validated['nom_fournisseur'] ?? null,
            'seuil_pct' => $validated['seuil_pct'] ?? null,
            'count_nb_sms' => $validated['count_nb_sms'] ?? null,
            'motif' => $validated['motif'] ?? null,
            'status' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->notificationService->notifyFraudAlert(
                (string) ($validated['keyword'] ?? 'N/A'),
                (string) ($validated['motif'] ?? 'Alerte fraude detectee')
            );
        } catch (\Throwable $exception) {
            Log::warning('Fraud notification dispatch failed after alert create', [
                'alert_id' => $id,
                'message' => $exception->getMessage(),
            ]);
        }

        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'alerte_id' => $id,
                    'keyword' => $validated['keyword'],
                    'motif' => $validated['motif'] ?? 'Alerte fraude',
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for alerte_create', ['error' => $e->getMessage()]);
        }

        $alert = DB::table('ra_t_alerts')->find($id);
        $this->auditLog->log('create', 'alerte', "Alerte créée sur " . ($alert->keyword ?? 'N/A') . " (Motif: " . ($alert->motif ?? 'N/A') . ")", [], (array)$alert, 'succes', $id);

        return response()->json($alert, 201);
    }

    /**
     * Met à jour une alerte. Pour résoudre : { "status": true }.
     */
    public function update(Request $request, $id)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'alerte_update',
                'systeme',
                null,
                0,
                ['page' => 'Alertes fraude', 'alerte_id' => $id]
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for alerte_update', ['error' => $e->getMessage()]);
        }

        $alert = DB::table('ra_t_alerts')->find($id);
        if (! $alert) {
            try {
                if ($jobId) {
                    $this->monitor->failJob($jobId, 'Alerte introuvable');
                }
            } catch (\Exception $e) {
                Log::warning('EtlMonitorService failJob failed for alerte_update', ['error' => $e->getMessage()]);
            }
            return response()->json(['message' => 'Alerte introuvable'], 404);
        }

        $validated = $request->validate([
            'status' => 'sometimes|boolean',
            'start_date' => 'sometimes|date',
            'nom_service' => 'nullable|string|max:100',
            'numero_court' => 'nullable|string|max:20',
            'keyword' => 'nullable|string|max:20',
            'nom_fournisseur' => 'nullable|string|max:100',
            'seuil_pct' => 'nullable|numeric|min:0|max:100',
            'count_nb_sms' => 'nullable|integer|min:0',
            'motif' => 'nullable|string|max:255',
        ]);

        $payload = ['updated_at' => now()];

        if (array_key_exists('status', $validated)) {
            $payload['status'] = $request->boolean('status');
        }
        foreach (['start_date', 'nom_service', 'numero_court', 'keyword', 'nom_fournisseur', 'seuil_pct', 'count_nb_sms', 'motif'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        $avant = (array)$alert;
        DB::table('ra_t_alerts')->where('id', $id)->update($payload);
        $apres = (array) DB::table('ra_t_alerts')->find($id);

        $description = "Alerte modifiée";
        if (isset($payload['status'])) {
            $description = $payload['status'] ? "Alerte résolue" : "Alerte réouverte";
        }

        $this->auditLog->log('update', 'alerte', $description, $avant, $apres, 'succes', $id);

        return response()->json($apres);
    }
}
