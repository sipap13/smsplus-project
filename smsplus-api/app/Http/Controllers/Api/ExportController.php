<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    public function revenusCsv(Request $request)
    {
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

        $filename = 'revenus_export_' . now()->format('Ymd_His') . '.csv';

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
}
