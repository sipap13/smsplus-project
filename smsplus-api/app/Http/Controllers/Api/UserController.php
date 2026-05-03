<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EtlMonitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function __construct(
        protected EtlMonitorService $monitor,
        protected \App\Services\AuditLogService $auditLog,
    ) {}
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
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'user_create',
                'systeme',
                null,
                0,
                ['page' => 'Utilisateurs', 'role' => $request->role]
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for user_create', ['error' => $e->getMessage()]);
        }

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
            'two_fa_enabled' => true,
            'two_fa_method'  => 'email',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = DB::table('ra_t_users')->select('id', 'nom', 'email', 'numero_personnel', 'direction', 'role', 'tel', 'actif', 'last_login_at', 'created_at')->find($id);

        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for user_create', ['error' => $e->getMessage()]);
        }

        $this->auditLog->log('create', 'utilisateur', "Utilisateur {$user->email} créé (Rôle: {$user->role})", [], (array)$user, 'succes', $user->id);

        return response()->json($user, 201);
    }

    public function update(Request $request, $id)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'user_update_role',
                'systeme',
                null,
                0,
                ['page' => 'Utilisateurs', 'user_id' => $id]
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for user_update_role', ['error' => $e->getMessage()]);
        }

        if (! DB::table('ra_t_users')->where('id', $id)->exists()) {
            try {
                if ($jobId) {
                    $this->monitor->failJob($jobId, 'Utilisateur introuvable');
                }
            } catch (\Exception $e) {
                Log::warning('EtlMonitorService failJob failed for user_update_role', ['error' => $e->getMessage()]);
            }
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

        $avant = (array) DB::table('ra_t_users')->find($id);
        DB::table('ra_t_users')->where('id', $id)->update($payload);

        $user = DB::table('ra_t_users')->select('id', 'nom', 'email', 'numero_personnel', 'direction', 'role', 'tel', 'actif', 'last_login_at', 'created_at')->find($id);
        $apres = (array) $user;

        $description = "Utilisateur {$user->email} modifié";
        if (isset($payload['actif'])) {
            $description = $payload['actif'] ? "Compte {$user->email} activé" : "Compte {$user->email} désactivé";
        } elseif (isset($payload['role'])) {
            $description = "Rôle modifié pour {$user->email} : {$payload['role']}";
        }

        $this->auditLog->log('update', 'utilisateur', $description, $avant, $apres, 'succes', $id);

        return response()->json($user);
    }
}
