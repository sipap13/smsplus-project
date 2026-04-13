<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CdrController extends Controller
{
    private const PER_PAGE_DEFAULT = 50;

    private const PER_PAGE_MAX = 50;

    private const MSISDN_RESULT_LIMIT = 2000;

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
        'ne',
        'a_msisdn',
        'b_msisdn',
        'start_date',
        'start_hour',
        'event_type',
        'event_status',
        'subscriber_type',
        'service_type',
    ];

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function occFilteredQuery(Request $request)
    {
        $q = DB::table('ra_t_occ_cdr_detail');

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
        $q = DB::table('ra_t_mmg_cdr_det');

        $startDate = trim((string) $request->query('start_date', ''));
        if ($startDate !== '') {
            $q->where('start_date', $startDate);
        }

        $subscriberType = trim((string) $request->query('subscriber_type', ''));
        if ($subscriberType !== '') {
            $q->where('subscriber_type', $subscriberType);
        }

        $eventStatus = trim((string) $request->query('event_status', ''));
        if ($eventStatus !== '') {
            if (strcasecmp($eventStatus, 'Success') === 0) {
                $q->whereRaw('LOWER(TRIM(COALESCE(event_status, \'\'))) = ?', ['success']);
            } elseif (strcasecmp($eventStatus, 'Failed') === 0) {
                $q->where(function ($w) {
                    $w->whereRaw('LOWER(TRIM(COALESCE(event_status, \'\'))) = ?', ['failed'])
                        ->orWhereRaw('LOWER(TRIM(COALESCE(event_status, \'\'))) LIKE ?', ['%fail%']);
                });
            }
        }

        return $q;
    }

    public function occ(Request $request)
    {
        $perPage = (int) $request->query('per_page', self::PER_PAGE_DEFAULT);
        $perPage = max(1, min($perPage, self::PER_PAGE_MAX));
        $page = max(1, (int) $request->query('page', 1));

        $base = $this->occFilteredQuery($request);
        $total = (clone $base)->count();
        $totalCharge = (float) (clone $base)->sum('charge_amount');
        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 0;
        if ($lastPage > 0 && $page > $lastPage) {
            $page = $lastPage;
        }

        $data = (clone $base)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get(self::OCC_SELECT);

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
        $perPage = (int) $request->query('per_page', self::PER_PAGE_DEFAULT);
        $perPage = max(1, min($perPage, self::PER_PAGE_MAX));
        $page = max(1, (int) $request->query('page', 1));

        $base = $this->mmgFilteredQuery($request);
        $total = (clone $base)->count();
        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 0;
        if ($lastPage > 0 && $page > $lastPage) {
            $page = $lastPage;
        }

        $data = (clone $base)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get(self::MMG_SELECT);

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
        $norm = $this->normalizeMsisdn($msisdn);
        if (strlen($norm) < 5 || strlen($norm) > 32) {
            return response()->json(['message' => 'MSISDN invalide'], 422);
        }

        $limit = self::MSISDN_RESULT_LIMIT;

        $occBase = DB::table('ra_t_occ_cdr_detail')->where(function ($w) use ($norm) {
            $w->where('a_msisdn', $norm)->orWhere('b_msisdn', $norm);
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
        ]);
    }

    private function normalizeMsisdn(string $raw): string
    {
        $s = preg_replace('/\s+/', '', $raw) ?? '';
        $s = ltrim($s, '+');

        return $s;
    }
}
