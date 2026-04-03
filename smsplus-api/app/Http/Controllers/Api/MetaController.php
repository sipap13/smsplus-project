<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MetaController extends Controller
{
    public function dashboardRange(Request $request)
    {
        $includeData = in_array(strtolower((string) $request->query('include_data', '0')), ['1', 'true', 'yes'], true);
        $allowedCallTypes = ['VAS', 'SMS', 'VOICE'];

        $cacheKey = 'dashboard_range_' . ($includeData ? 'with_data' : 'no_data');

        $range = Cache::remember($cacheKey, 600, function () use ($includeData, $allowedCallTypes) {
            $q = DB::table('ra_t_occ_cdr_detail');
            if (! $includeData) {
                $q->whereIn('call_type', $allowedCallTypes);
            }

            return [
                'min_date' => $q->min('start_date'),
                'max_date' => $q->max('start_date'),
            ];
        });

        return response()->json($range);
    }
}

