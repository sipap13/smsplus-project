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

        $token = bin2hex(random_bytes(32));

        DB::table('ra_t_users')
            ->where('id', $user->id)
            ->update(['updated_at' => now()]);

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'        => $user->id,
                'email'     => $user->email,
                'role'      => $user->role,
                'direction' => $user->direction,
            ],
        ]);
    }

    public function logout()
    {
        return response()->json(['message' => 'Déconnecté avec succès']);
    }

    public function me(Request $request)
    {
        $email = $request->header('X-User-Email');
        $user  = DB::table('ra_t_users')->where('email', $email)->first();

        if (!$user) {
            return response()->json(['message' => 'Non autorisé'], 401);
        }

        return response()->json([
            'id'        => $user->id,
            'email'     => $user->email,
            'role'      => $user->role,
            'direction' => $user->direction,
        ]);
    }
}