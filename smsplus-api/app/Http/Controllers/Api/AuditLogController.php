<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query();

        // Filters
        if ($request->filled('user_email')) {
            $query->where('user_email', $request->user_email);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('entite')) {
            $query->where('entite', $request->entite);
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('date_debut')) {
            $query->whereDate('created_at', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->whereDate('created_at', '<=', $request->date_fin);
        }
        if ($request->filled('search')) {
            $query->where('description', 'LIKE', '%' . $request->search . '%');
        }

        $perPage = $request->input('per_page', 25);
        $logs = $query->orderByDesc('created_at')->paginate($perPage);

        // Stats for the response
        $today = now()->toDateString();
        $totalActions = AuditLog::count();
        $loginsToday = AuditLog::where('action', 'login')->whereDate('created_at', $today)->count();
        $failedActions = AuditLog::where('statut', 'echec')->count();
        
        $topUser = AuditLog::select('user_email', DB::raw('count(*) as count'))
            ->whereNotNull('user_email')
            ->groupBy('user_email')
            ->orderByDesc('count')
            ->first();

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
            'stats' => [
                'total_actions' => $totalActions,
                'logins_aujourd_hui' => $loginsToday,
                'actions_echec' => $failedActions,
                'top_utilisateur' => $topUser ? $topUser->user_email : 'N/A',
            ]
        ]);
    }

    public function stats()
    {
        // Actions by hour (last 24 hours)
        $last24h = now()->subHours(24);
        $actionsByHour = AuditLog::select(
                DB::raw("DATE_TRUNC('hour', created_at) as hour"),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', $last24h)
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // Actions by day (last 7 days)
        $last7d = now()->subDays(7);
        $actionsByDay = AuditLog::select(
                DB::raw("DATE_TRUNC('day', created_at) as day"),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', $last7d)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // Top users
        $topUsers = AuditLog::select('user_email', 'user_role', DB::raw('count(*) as total_actions'), DB::raw('max(created_at) as last_action'))
            ->selectRaw("count(*) FILTER (WHERE statut = 'echec') as failed_actions")
            ->whereNotNull('user_email')
            ->groupBy('user_email', 'user_role')
            ->orderByDesc('total_actions')
            ->limit(10)
            ->get();

        return response()->json([
            'by_hour' => $actionsByHour,
            'by_day' => $actionsByDay,
            'top_users' => $topUsers,
        ]);
    }
}
