<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index()
    {
        return response()->json(DB::table('ra_t_services')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nom_fournisseur' => 'required|string',
            'nom_service'     => 'required|string',
            'numero_court'    => 'required|string',
            'keyword'         => 'required|string',
            'prix'            => 'required|numeric',
        ]);

        $id = DB::table('ra_t_services')->insertGetId(array_merge(
            $request->only(['nom_fournisseur', 'nom_service', 'numero_court', 'keyword', 'type_service', 'prix']),
            ['actif' => true, 'created_at' => now(), 'updated_at' => now()]
        ));

        return response()->json(DB::table('ra_t_services')->find($id), 201);
    }

    public function show($id)
    {
        return response()->json(DB::table('ra_t_services')->find($id));
    }

    public function update(Request $request, $id)
    {
        DB::table('ra_t_services')->where('id', $id)->update(array_merge(
            $request->only(['nom_fournisseur', 'nom_service', 'numero_court', 'keyword', 'type_service', 'prix', 'actif']),
            ['updated_at' => now()]
        ));

        return response()->json(DB::table('ra_t_services')->find($id));
    }

    public function destroy($id)
    {
        DB::table('ra_t_services')->where('id', $id)->delete();
        return response()->json(['message' => 'Service supprimé']);
    }
}
