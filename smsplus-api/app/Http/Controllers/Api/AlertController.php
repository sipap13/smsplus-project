<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlertController extends Controller
{
    public function index()
    {
        $alerts = DB::table('ra_t_alerts')->orderBy('start_date', 'desc')->orderBy('id', 'desc')->get();

        return response()->json($alerts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'start_date'      => 'required|date',
            'nom_service'     => 'nullable|string|max:100',
            'numero_court'    => 'nullable|string|max:20',
            'keyword'         => 'nullable|string|max:20',
            'nom_fournisseur' => 'nullable|string|max:100',
            'seuil_pct'       => 'nullable|numeric|min:0|max:100',
            'count_nb_sms'    => 'nullable|integer|min:0',
            'motif'           => 'nullable|string|max:255',
        ]);

        $id = DB::table('ra_t_alerts')->insertGetId([
            'start_date'      => $validated['start_date'],
            'nom_service'     => $validated['nom_service'] ?? null,
            'numero_court'    => $validated['numero_court'] ?? null,
            'keyword'         => $validated['keyword'] ?? null,
            'nom_fournisseur' => $validated['nom_fournisseur'] ?? null,
            'seuil_pct'       => $validated['seuil_pct'] ?? null,
            'count_nb_sms'    => $validated['count_nb_sms'] ?? null,
            'motif'           => $validated['motif'] ?? null,
            'status'          => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json(DB::table('ra_t_alerts')->find($id), 201);
    }

    /**
     * Met à jour une alerte. Pour résoudre : { "status": true }.
     */
    public function update(Request $request, $id)
    {
        $alert = DB::table('ra_t_alerts')->find($id);
        if (! $alert) {
            return response()->json(['message' => 'Alerte introuvable'], 404);
        }

        $validated = $request->validate([
            'status'          => 'sometimes|boolean',
            'start_date'      => 'sometimes|date',
            'nom_service'     => 'nullable|string|max:100',
            'numero_court'    => 'nullable|string|max:20',
            'keyword'         => 'nullable|string|max:20',
            'nom_fournisseur' => 'nullable|string|max:100',
            'seuil_pct'       => 'nullable|numeric|min:0|max:100',
            'count_nb_sms'    => 'nullable|integer|min:0',
            'motif'           => 'nullable|string|max:255',
        ]);

        $payload = ['updated_at' => now()];

        if (array_key_exists('status', $validated)) {
            $payload['status'] = $request->boolean('status');
        }
        foreach (['start_date', 'nom_service', 'numero_court', 'keyword', 'nom_fournisseur', 'seuil_pct', 'count_nb_sms', 'motif'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        DB::table('ra_t_alerts')->where('id', $id)->update($payload);

        return response()->json(DB::table('ra_t_alerts')->find($id));
    }
}
