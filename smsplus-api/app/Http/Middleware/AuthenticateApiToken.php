<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $auth = (string) $request->header('Authorization', '');
        $token = null;
        if (preg_match('/^Bearer\s+(?<token>\S+)$/i', $auth, $m)) {
            $token = $m['token'] ?? null;
        }

        if (! $token) {
            return response()->json(['message' => 'Non autorisé'], 401);
        }

        $hash = hash('sha256', $token);

        $row = DB::table('ra_t_api_tokens')
            ->join('ra_t_users', 'ra_t_users.id', '=', 'ra_t_api_tokens.user_id')
            ->where('ra_t_api_tokens.token_hash', $hash)
            ->where('ra_t_users.actif', true)
            ->select([
                'ra_t_users.id as id',
                'ra_t_users.email as email',
                'ra_t_users.role as role',
                'ra_t_users.direction as direction',
                'ra_t_api_tokens.expires_at as expires_at',
            ])
            ->first();

        if (! $row) {
            return response()->json(['message' => 'Non autorisé'], 401);
        }

        if ($row->expires_at && now()->greaterThan($row->expires_at)) {
            return response()->json(['message' => 'Token expiré'], 401);
        }

        // Attach user context for controllers.
        $request->attributes->set('auth_user', $row);

        return $next($request);
    }
}

