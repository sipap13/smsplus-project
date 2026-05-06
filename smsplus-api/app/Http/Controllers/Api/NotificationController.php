<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\EtlMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function __construct(
        protected EtlMonitorService $monitor,
    ) {}
    public function index(Request $request): JsonResponse
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'notifications_load',
                'systeme',
                null,
                0,
                ['page' => 'Notifications', 'triggered_by' => 'user']
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for notifications_load', ['error' => $e->getMessage()]);
        }

        try {
            $user = $request->attributes->get('auth_user');
            $userId = (int) ($user->id ?? 0);

            $notifications = Notification::query()
                ->where('user_id', $userId)
                ->latest()
                ->limit(50)
                ->get();

            $nonLues = Notification::query()
                ->where('user_id', $userId)
                ->where('lue', false)
                ->count();

            try {
                if ($jobId) {
                    $this->monitor->finishJob($jobId, 'success', null, [
                        'user_id' => $userId,
                        'nb_notifications' => $notifications->count(),
                        'nb_non_lues' => $nonLues,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('EtlMonitorService finishJob failed for notifications_load', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'notifications' => $notifications,
                'non_lues' => $nonLues,
                'total' => $notifications->count(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Notifications index error', ['message' => $exception->getMessage()]);

            try {
                if ($jobId) {
                    $this->monitor->finishJob($jobId, 'failed', 'Error loading notifications');
                }
            } catch (\Exception $e) {
                Log::warning('EtlMonitorService finishJob failed for notifications_load', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'notifications' => [],
                'non_lues' => 0,
                'total' => 0,
                'message' => 'Notifications momentanement indisponibles',
            ]);
        }
    }

    public function count(Request $request): JsonResponse
    {
        $jobId = null;
        try {
            // Un job très court pour signaler l'activité de polling dans le JobStatusBar
            $jobId = $this->monitor->startJob(
                'notifications_polling',
                'systeme',
                null,
                0,
                ['triggered_by' => 'system']
            );

            $user = $request->attributes->get('auth_user');
            $userId = (int) ($user->id ?? 0);

            $nonLues = Notification::query()
                ->where('user_id', $userId)
                ->where('lue', false)
                ->count();

            $hasCritical = Notification::query()
                ->where('user_id', $userId)
                ->where('lue', false)
                ->where('priorite', 'critique')
                ->exists();

            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'user_id' => $userId,
                    'non_lues' => $nonLues,
                    'processed_rows' => 1, // Une requête de polling = 1 unité de travail
                ]);
            }

            return response()->json([
                'non_lues' => $nonLues,
                'has_critique' => $hasCritical,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Notifications count error', ['message' => $exception->getMessage()]);

            if ($jobId) {
                $this->monitor->finishJob($jobId, 'failed', $exception->getMessage());
            }

            return response()->json([
                'non_lues' => 0,
                'has_critique' => false,
                'message' => 'Notifications momentanement indisponibles',
            ]);
        }
    }

    public function lire(Request $request, int $id): JsonResponse
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'notification_mark_read',
                'systeme',
                null,
                0,
                ['page' => 'Notifications', 'notification_id' => $id]
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for notification_mark_read', ['error' => $e->getMessage()]);
        }

        $user = $request->attributes->get('auth_user');
        $userId = (int) ($user->id ?? 0);

        $notification = Notification::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (! $notification) {
            try {
                if ($jobId) {
                    $this->monitor->finishJob($jobId, 'failed', 'Notification introuvable');
                }
            } catch (\Exception $e) {
                Log::warning('EtlMonitorService finishJob failed for notification_mark_read', ['error' => $e->getMessage()]);
            }
            return response()->json(['message' => 'Notification introuvable'], 404);
        }

        $notification->update([
            'lue' => true,
            'lue_at' => now(),
        ]);

        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'notification_id' => $id,
                    'user_id' => $userId,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for notification_mark_read', ['error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Notification marquee comme lue']);
    }

    public function lireTout(Request $request): JsonResponse
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'notifications_mark_all_read',
                'systeme',
                null,
                0,
                ['page' => 'Notifications']
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for notifications_mark_all_read', ['error' => $e->getMessage()]);
        }

        $user = $request->attributes->get('auth_user');
        $userId = (int) ($user->id ?? 0);

        $count = Notification::query()
            ->where('user_id', $userId)
            ->where('lue', false)
            ->count();

        Notification::query()
            ->where('user_id', $userId)
            ->where('lue', false)
            ->update(['lue' => true, 'lue_at' => now(), 'updated_at' => now()]);

        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'user_id' => $userId,
                    'nb_marquees_lues' => $count,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for notifications_mark_all_read', ['error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Toutes les notifications sont marquees comme lues']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $userId = (int) ($user->id ?? 0);

        $notification = Notification::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (! $notification) {
            return response()->json(['message' => 'Notification introuvable'], 404);
        }

        $notification->delete();

        return response()->json(['message' => 'Notification supprimee']);
    }

    public function vider(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $userId = (int) ($user->id ?? 0);

        Notification::query()
            ->where('user_id', $userId)
            ->where('lue', true)
            ->delete();

        return response()->json(['message' => 'Notifications lues supprimees']);
    }
}
