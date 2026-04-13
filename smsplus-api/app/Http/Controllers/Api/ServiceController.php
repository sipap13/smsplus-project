<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index()
    {
        return response()->json(DB::table('ra_t_services')->orderBy('id')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom_fournisseur' => 'required|string|max:100',
            'nom_service'     => 'required|string|max:100',
            'numero_court'    => 'required|string|max:20',
            'keyword'         => 'required|string|max:20',
            'type_service'    => 'nullable|in:Service,jeu',
            'prix'            => 'required|numeric|min:0',
            'actif'           => 'sometimes|boolean',
        ]);

        $id = DB::table('ra_t_services')->insertGetId([
            'nom_fournisseur' => $validated['nom_fournisseur'],
            'nom_service'     => $validated['nom_service'],
            'numero_court'    => $validated['numero_court'],
            'keyword'         => $validated['keyword'],
            'type_service'    => $validated['type_service'] ?? null,
            'prix'            => $validated['prix'],
            'actif'           => $request->boolean('actif', true),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json(DB::table('ra_t_services')->find($id), 201);
    }

    public function show($id)
    {
        $row = DB::table('ra_t_services')->find($id);
        if (! $row) {
            return response()->json(['message' => 'Service introuvable'], 404);
        }

        return response()->json($row);
    }

    public function update(Request $request, $id)
    {
        if (! DB::table('ra_t_services')->where('id', $id)->exists()) {
            return response()->json(['message' => 'Service introuvable'], 404);
        }

        $validated = $request->validate([
            'nom_fournisseur' => 'sometimes|required|string|max:100',
            'nom_service'     => 'sometimes|required|string|max:100',
            'numero_court'    => 'sometimes|required|string|max:20',
            'keyword'         => 'sometimes|required|string|max:20',
            'type_service'    => 'nullable|in:Service,jeu',
            'prix'            => 'sometimes|required|numeric|min:0',
            'actif'           => 'sometimes|boolean',
        ]);

        $payload = array_merge(
            array_intersect_key($validated, array_flip([
                'nom_fournisseur', 'nom_service', 'numero_court', 'keyword', 'type_service', 'prix',
            ])),
            ['updated_at' => now()]
        );

        if ($request->has('actif')) {
            $payload['actif'] = $request->boolean('actif');
        }

        if (count($payload) > 1) {
            DB::table('ra_t_services')->where('id', $id)->update($payload);
        }

        return response()->json(DB::table('ra_t_services')->find($id));
    }

    public function destroy($id)
    {
        $deleted = DB::table('ra_t_services')->where('id', $id)->delete();
        if (! $deleted) {
            return response()->json(['message' => 'Service introuvable'], 404);
        }

        return response()->json(['message' => 'Service supprimé']);
    }
}
