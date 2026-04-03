<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = DB::table('ra_t_users')
            ->where('email', $request->email)
            ->where('actif', true)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email ou mot de passe incorrect'], 401);
        }

        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        DB::table('ra_t_api_tokens')->insert([
            'user_id'    => $user->id,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ra_t_users')
            ->where('id', $user->id)
            ->update(['updated_at' => now()]);

        return response()->json([
            'token' => $plainToken,
            'user'  => [
                'id'        => $user->id,
                'email'     => $user->email,
                'role'      => $user->role,
                'direction' => $user->direction,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $auth = (string) $request->header('Authorization', '');
        $token = null;
        if (preg_match('/^Bearer\s+(?<token>\S+)$/i', $auth, $m)) {
            $token = $m['token'] ?? null;
        }

        if ($token) {
            DB::table('ra_t_api_tokens')->where('token_hash', hash('sha256', $token))->delete();
        }

        return response()->json(['message' => 'Déconnecté avec succès']);
    }

    public function me(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        if (! $user) return response()->json(['message' => 'Non autorisé'], 401);

        return response()->json([
            'id'        => $user->id,
            'email'     => $user->email,
            'role'      => $user->role,
            'direction' => $user->direction,
        ]);
    }
}