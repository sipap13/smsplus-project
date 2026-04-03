<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlertController extends Controller
{
    public function index()
    {
        $alerts = DB::table('ra_t_alerts')->orderBy('start_date', 'desc')->get();
        return response()->json($alerts);
    }

    public function resolve($id)
    {
        DB::table('ra_t_alerts')->where('id', $id)->update([
            'status'     => true,
            'updated_at' => now(),
        ]);
        return response()->json(DB::table('ra_t_alerts')->find($id));
    }
}
