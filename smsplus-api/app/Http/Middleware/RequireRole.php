<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->attributes->get('auth_user');
        $role = is_object($user) ? ($user->role ?? null) : null;

        if (! $role || ! in_array($role, $roles, true)) {
            return response()->json(['message' => 'Accès interdit'], 403);
        }

        return $next($request);
    }
}

