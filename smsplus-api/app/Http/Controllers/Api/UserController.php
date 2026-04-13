<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = DB::table('ra_t_users')
            ->select('id', 'nom', 'email', 'numero_personnel', 'direction', 'role', 'tel', 'actif', 'last_login_at', 'created_at')
            ->orderBy('id')
            ->get();

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email'     => 'required|email|max:150|unique:ra_t_users,email',
            'password'  => 'required|string|min:6|max:255',
            'role'      => 'required|in:ADMIN,ANALYSTE_OP,ANALYSTE_BUSS',
            'nom'       => 'nullable|string|max:150',
            'numero_personnel' => 'nullable|string|max:50',
            'direction' => 'nullable|string|max:100',
            'tel'       => 'nullable|string|max:20',
        ]);

        $id = DB::table('ra_t_users')->insertGetId([
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
            'nom'        => $validated['nom'] ?? null,
            'numero_personnel' => $validated['numero_personnel'] ?? null,
            'direction'  => $validated['direction'] ?? 'Assurance et Fraude',
            'role'       => $validated['role'],
            'tel'        => $validated['tel'] ?? null,
            'actif'      => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(
            DB::table('ra_t_users')->select('id', 'nom', 'email', 'numero_personnel', 'direction', 'role', 'tel', 'actif', 'last_login_at', 'created_at')->find($id),
            201
        );
    }

    public function update(Request $request, $id)
    {
        if (! DB::table('ra_t_users')->where('id', $id)->exists()) {
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }

        $validated = $request->validate([
            'actif'     => 'sometimes|boolean',
            'nom'       => 'nullable|string|max:150',
            'numero_personnel' => 'nullable|string|max:50',
            'direction' => 'nullable|string|max:100',
            'tel'       => 'nullable|string|max:20',
            'role'      => 'sometimes|in:ADMIN,ANALYSTE_OP,ANALYSTE_BUSS',
            'password'  => 'nullable|string|min:6|max:255',
        ]);

        $payload = ['updated_at' => now()];

        if ($request->has('actif')) {
            $payload['actif'] = $request->boolean('actif');
        }
        if (array_key_exists('nom', $validated)) {
            $payload['nom'] = $validated['nom'];
        }
        if (array_key_exists('numero_personnel', $validated)) {
            $payload['numero_personnel'] = $validated['numero_personnel'];
        }
        if (array_key_exists('direction', $validated)) {
            $payload['direction'] = $validated['direction'];
        }
        if (array_key_exists('tel', $validated)) {
            $payload['tel'] = $validated['tel'];
        }
        if (array_key_exists('role', $validated)) {
            $payload['role'] = $validated['role'];
        }
        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        DB::table('ra_t_users')->where('id', $id)->update($payload);

        return response()->json(
            DB::table('ra_t_users')->select('id', 'nom', 'email', 'numero_personnel', 'direction', 'role', 'tel', 'actif', 'last_login_at', 'created_at')->find($id)
        );
    }
}
