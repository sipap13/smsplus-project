<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reclamation;

class ReclamationController extends Controller
{
    public function byMsisdn($msisdn)
    {
        $reclamations = Reclamation::with('service')
            ->where('msisdn', $msisdn)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($reclamations);
    }
}