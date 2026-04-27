<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
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

            return response()->json([
                'notifications' => $notifications,
                'non_lues' => $nonLues,
                'total' => $notifications->count(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Notifications index error', ['message' => $exception->getMessage()]);

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
        try {
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

            return response()->json([
                'non_lues' => $nonLues,
                'has_critique' => $hasCritical,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Notifications count error', ['message' => $exception->getMessage()]);

            return response()->json([
                'non_lues' => 0,
                'has_critique' => false,
                'message' => 'Notifications momentanement indisponibles',
            ]);
        }
    }

    public function lire(Request $request, int $id): JsonResponse
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

        $notification->update([
            'lue' => true,
            'lue_at' => now(),
        ]);

        return response()->json(['message' => 'Notification marquee comme lue']);
    }

    public function lireTout(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $userId = (int) ($user->id ?? 0);

        Notification::query()
            ->where('user_id', $userId)
            ->where('lue', false)
            ->update(['lue' => true, 'lue_at' => now(), 'updated_at' => now()]);

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
