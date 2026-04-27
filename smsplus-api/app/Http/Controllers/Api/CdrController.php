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

    public function timeline(string $msisdn, Request $request)
    {
        $norm = $this->normalizeMsisdn($msisdn);
        if (strlen($norm) < 5 || strlen($norm) > 32) {
            return response()->json(['message' => 'MSISDN invalide'], 422);
        }

        $dateFin   = $request->query('date_fin')   ? \Carbon\Carbon::parse($request->query('date_fin'))->format('Y-m-d') : now()->format('Y-m-d');
        $dateDebut = $request->query('date_debut') ? \Carbon\Carbon::parse($request->query('date_debut'))->format('Y-m-d') : now()->subDays(30)->format('Y-m-d');
        $source    = in_array($request->query('source'), ['occ', 'mmg', 'all']) ? $request->query('source') : 'all';

        $services = DB::table('ra_t_services')
            ->whereNotNull('keyword')
            ->where('keyword', '!=', '')
            ->pluck('nom_service', 'keyword')
            ->toArray();

        $items = [];

        if ($source === 'occ' || $source === 'all') {
            $occRows = DB::table('ra_t_occ_cdr_detail')
                ->where('a_msisdn', $norm)
                ->whereBetween('start_date', [$dateDebut, $dateFin])
                ->orderByDesc('start_date')
                ->orderByDesc('start_hour')
                ->limit(200)
                ->get([
                    'id',
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
                $dt = $this->buildDatetime($row->start_date, $row->start_hour, $row->orig_start_time);
                $items[] = [
                    'id'              => (int) $row->id,
                    'source'          => 'OCC',
                    'date'            => $row->start_date,
                    'heure'           => (int) $row->start_hour,
                    'datetime'        => $dt,
                    'service'         => $row->keyword,
                    'nom_service'     => $services[$row->keyword] ?? strtoupper($row->keyword ?? ''),
                    'montant'         => (float) $row->charge_amount,
                    'destinataire'    => $row->b_msisdn,
                    'statut'          => 'Success',
                    'type'            => $row->event_type ?? $row->call_type ?? 'VAS',
                    'subscriber_type' => $row->subscriber_type,
                    'raw'             => $row,
                ];
            }
        }

        if ($source === 'mmg' || $source === 'all') {
            $mmgRows = DB::table('ra_t_mmg_cdr_det')
                ->where('a_msisdn', $norm)
                ->whereBetween('start_date', [$dateDebut, $dateFin])
                ->orderByDesc('start_date')
                ->orderByDesc('start_hour')
                ->limit(200)
                ->get([
                    'id',
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
                $dt = $this->buildDatetime($row->start_date, $row->start_hour, $row->orig_start_time);
                $items[] = [
                    'id'              => (int) $row->id,
                    'source'          => 'MMG',
                    'date'            => $row->start_date,
                    'heure'           => (int) $row->start_hour,
                    'datetime'        => $dt,
                    'service'         => $row->service_type,
                    'nom_service'     => $services[$row->service_type] ?? strtoupper($row->service_type ?? ''),
                    'montant'         => 0.00,
                    'destinataire'    => $row->b_msisdn,
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

        $parJour = [];
        $servicesUtilises = [];
        $totalRevenus = 0;
        $datesUniques = [];

        foreach ($items as $it) {
            $d = $it['date'];
            if (!isset($parJour[$d])) {
                $parJour[$d] = ['date' => $d, 'nb_transactions' => 0, 'montant_total' => 0.0, 'sources' => []];
            }
            $parJour[$d]['nb_transactions']++;
            $parJour[$d]['montant_total'] += $it['montant'];
            $parJour[$d]['sources'][] = $it['source'];
            $parJour[$d]['sources'] = array_values(array_unique($parJour[$d]['sources']));

            $totalRevenus += $it['montant'];

            if ($it['service'] && !in_array($it['service'], $servicesUtilises)) {
                $servicesUtilises[] = $it['service'];
            }

            $datesUniques[$d] = true;
        }

        $parJour = array_values($parJour);
        usort($parJour, fn ($a, $b) => strcmp($b['date'], $a['date']));

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

        return response()->json([
            'msisdn'            => $norm,
            'periode'           => ['debut' => $dateDebut, 'fin' => $dateFin],
            'total_transactions'=> count($items),
            'total_revenus'     => round($totalRevenus, 3),
            'timeline'          => $items,
            'par_jour'          => $parJour,
            'services_utilises' => $servicesUtilises,
            'premier_contact'   => $premierContact,
            'dernier_contact'   => $dernierContact,
            'gaps'              => $gaps,
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
}
