<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DuplicateController extends Controller
{
    public function __construct(
        protected \App\Services\AuditLogService $auditLog,
    ) {}
    /**
     * Détecter les doublons OCC.
     */
    public function detectOcc(Request $request)
    {
        $dateDebut = $request->query('date_debut', now()->subDays(30)->toDateString());
        $keyword = $request->query('keyword');
        $callType = $request->query('call_type', 'VAS');
        $minOccurrences = (int) $request->query('min_occurrences', 2);

        $query = DB::table('ra_t_occ_cdr_detail')
            ->select([
                'a_msisdn',
                'b_msisdn',
                'start_date',
                'start_hour',
                'charge_amount',
                'keyword',
                'call_type',
                DB::raw('COUNT(*) as occurrences'),
                DB::raw('array_agg(id) as ids'),
                DB::raw('SUM(charge_amount) as revenu_duplique')
            ])
            ->where('start_date', '>=', $dateDebut);

        if ($callType && $callType !== 'all') {
            $query->where('call_type', $callType);
        }

        if ($keyword) {
            $query->where('keyword', $keyword);
        }

        $results = $query->groupBy([
                'a_msisdn',
                'b_msisdn',
                'start_date',
                'start_hour',
                'charge_amount',
                'keyword',
                'call_type'
            ])
            ->havingRaw('COUNT(*) >= ?', [$minOccurrences])
            ->orderByDesc('occurrences')
            ->limit(1000)
            ->get();

        // Convertir ids de string array PostgreSQL "{1,2,3}" en array PHP
        $results->transform(function ($item) {
            if (is_string($item->ids)) {
                $item->ids = explode(',', str_replace(['{', '}'], '', $item->ids));
            }
            $item->revenu_duplique = (float) $item->revenu_duplique;
            $item->revenu_a_corriger = (float) ($item->charge_amount * ($item->occurrences - 1));
            return $item;
        });

        $this->auditLog->log('search', 'cdr', "Détection doublons OCC (Période: $dateDebut, KW: " . ($keyword ?: 'Tous') . ")", [], ['count' => $results->count()], 'succes');

        return response()->json($results);
    }

    /**
     * Détecter les doublons MMG.
     */
    public function detectMmg(Request $request)
    {
        $dateDebut = $request->query('date_debut', now()->subDays(30)->toDateString());
        $minOccurrences = (int) $request->query('min_occurrences', 2);

        $query = DB::table('ra_t_mmg_cdr_det')
            ->select([
                'a_msisdn',
                'b_msisdn',
                'start_date',
                'start_hour',
                'event_type',
                'service_type',
                DB::raw('COUNT(*) as occurrences'),
                DB::raw('array_agg(id) as ids'),
                DB::raw('0 as revenu_duplique')
            ])
            ->where('start_date', '>=', $dateDebut);

        $results = $query->groupBy([
                'a_msisdn',
                'b_msisdn',
                'start_date',
                'start_hour',
                'event_type',
                'service_type'
            ])
            ->havingRaw('COUNT(*) >= ?', [$minOccurrences])
            ->orderByDesc('occurrences')
            ->limit(1000)
            ->get();

        $results->transform(function ($item) {
            if (is_string($item->ids)) {
                $item->ids = explode(',', str_replace(['{', '}'], '', $item->ids));
            }
            return $item;
        });

        $this->auditLog->log('search', 'cdr', "Détection doublons MMG (Période: $dateDebut)", [], ['count' => $results->count()], 'succes');

        return response()->json($results);
    }

    /**
     * Statistiques globales sur les doublons.
     */
    public function stats(Request $request)
    {
        $dateDebut = $request->query('date_debut', now()->subDays(30)->toDateString());

        // Stats OCC
        $occStats = DB::table('ra_t_occ_cdr_detail')
            ->select([
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(charge_amount) as total_revenue')
            ])
            ->where('call_type', 'VAS')
            ->where('start_date', '>=', $dateDebut)
            ->groupBy([
                'a_msisdn',
                'b_msisdn',
                'start_date',
                'start_hour',
                'charge_amount',
                'keyword'
            ])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $occTotalDoublons = $occStats->count();
        $occAffectedCdr = $occStats->sum('total_count');
        $occRevenueImpact = $occStats->reduce(function ($carry, $item) {
            // Revenu compté en trop = (nombre - 1) * montant
            $montantUnitaire = $item->total_revenue / $item->total_count;
            return $carry + ($montantUnitaire * ($item->total_count - 1));
        }, 0);

        // Stats MMG
        $mmgStats = DB::table('ra_t_mmg_cdr_det')
            ->select([
                DB::raw('COUNT(*) as total_count')
            ])
            ->where('start_date', '>=', $dateDebut)
            ->groupBy([
                'a_msisdn',
                'b_msisdn',
                'start_date',
                'start_hour',
                'event_type',
                'service_type'
            ])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $mmgTotalDoublons = $mmgStats->count();
        $mmgAffectedCdr = $mmgStats->sum('total_count');

        // Top MSISDN (OCC)
        $topMsisdn = DB::table('ra_t_occ_cdr_detail')
            ->select('a_msisdn', DB::raw('COUNT(*) as count'))
            ->where('call_type', 'VAS')
            ->where('start_date', '>=', $dateDebut)
            ->groupBy('a_msisdn')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // Top Services (OCC)
        $topServices = DB::table('ra_t_occ_cdr_detail')
            ->select('keyword', DB::raw('COUNT(*) as count'))
            ->where('call_type', 'VAS')
            ->where('start_date', '>=', $dateDebut)
            ->groupBy('keyword')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // Répartition par date
        $byDate = DB::table('ra_t_occ_cdr_detail')
            ->select('start_date', DB::raw('COUNT(*) as count'))
            ->where('call_type', 'VAS')
            ->where('start_date', '>=', $dateDebut)
            ->groupBy('start_date')
            ->orderBy('start_date')
            ->get();

        return response()->json([
            'occ' => [
                'total_doublons' => $occTotalDoublons,
                'affected_cdr' => $occAffectedCdr,
                'revenue_impact' => round($occRevenueImpact, 3),
            ],
            'mmg' => [
                'total_doublons' => $mmgTotalDoublons,
                'affected_cdr' => $mmgAffectedCdr,
            ],
            'top_msisdn' => $topMsisdn,
            'top_services' => $topServices,
            'by_date' => $byDate,
            'total_affected' => $occAffectedCdr + $mmgAffectedCdr,
            'total_revenue_impact' => round($occRevenueImpact, 3),
        ]);
    }

    /**
     * Supprimer des doublons OCC spécifiques.
     */
    public function supprimerOcc(Request $request)
    {
        $ids = $request->input('ids');
        if (empty($ids) || !is_array($ids)) {
            return response()->json(['message' => 'IDs invalides'], 422);
        }

        // On garde le premier ID (le plus petit)
        sort($ids);
        $toKeep = array_shift($ids);
        $toDelete = $ids;

        if (empty($toDelete)) {
            return response()->json(['supprimes' => 0, 'revenus_corriges' => 0]);
        }

        $revenusCorriges = DB::table('ra_t_occ_cdr_detail')
            ->whereIn('id', $toDelete)
            ->sum('charge_amount');

        $count = DB::table('ra_t_occ_cdr_detail')
            ->whereIn('id', $toDelete)
            ->delete();

        $this->auditLog->log('delete', 'cdr', "Suppression manuelle de $count doublons OCC (Revenus corrigés: " . round($revenusCorriges, 3) . " DT)", [], [
            'ids_supprimes' => $toDelete,
            'id_garde' => $toKeep,
            'count' => $count,
            'revenus_corriges' => $revenusCorriges
        ], 'succes');

        return response()->json([
            'supprimes' => $count,
            'revenus_corriges' => round($revenusCorriges, 3)
        ]);
    }

    /**
     * Supprimer TOUS les doublons OCC.
     */
    public function supprimerTousOcc(Request $request)
    {
        // Admin uniquement
        if ($request->user() && $request->user()->role !== 'ADMIN') {
            return response()->json(['message' => 'Action réservée aux administrateurs'], 403);
        }

        $dateDebut = $request->input('date_debut', now()->subDays(90)->toDateString());

        // Trouver tous les groupes de doublons
        $groups = DB::table('ra_t_occ_cdr_detail')
            ->select([
                DB::raw('array_agg(id) as ids'),
                DB::raw('SUM(charge_amount) - MAX(charge_amount) as revenue_to_fix')
            ])
            ->where('call_type', 'VAS')
            ->where('start_date', '>=', $dateDebut)
            ->groupBy([
                'a_msisdn',
                'b_msisdn',
                'start_date',
                'start_hour',
                'charge_amount',
                'keyword'
            ])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $allToDelete = [];
        $totalRevenue = 0;

        foreach ($groups as $group) {
            $ids = explode(',', str_replace(['{', '}'], '', $group->ids));
            sort($ids);
            array_shift($ids); // Garder le premier
            $allToDelete = array_merge($allToDelete, $ids);
            $totalRevenue += $group->revenue_to_fix;
        }

        $count = 0;
        if (!empty($allToDelete)) {
            // Supprimer par lots pour éviter les problèmes de mémoire/taille de requête
            $chunks = array_chunk($allToDelete, 1000);
            foreach ($chunks as $chunk) {
                $count += DB::table('ra_t_occ_cdr_detail')
                    ->whereIn('id', $chunk)
                    ->delete();
            }
        }

        $this->auditLog->log('delete', 'cdr', "Suppression AUTOMATIQUE de $count doublons OCC (Revenus corrigés: " . round($totalRevenue, 3) . " DT)", [], [
            'count' => $count,
            'revenus_corriges' => $totalRevenue,
            'date_debut' => $dateDebut
        ], 'succes');

        return response()->json([
            'supprimes' => $count,
            'revenus_corriges' => round($totalRevenue, 3)
        ]);
    }
}
