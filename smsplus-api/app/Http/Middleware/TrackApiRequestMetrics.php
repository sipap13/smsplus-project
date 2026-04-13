<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackApiRequestMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);
        $statusCode = 500;
        $errorClass = null;

        try {
            $response = $next($request);
            $statusCode = (int) $response->getStatusCode();

            return $response;
        } catch (Throwable $e) {
            $errorClass = get_class($e);
            throw $e;
        } finally {
            $durationMs = (int) round((hrtime(true) - $start) / 1_000_000);
            $user = $request->attributes->get('auth_user');
            $userId = is_object($user) ? ($user->id ?? null) : null;

            try {
                DB::table('ra_t_api_request_metrics')->insert([
                    'path' => mb_substr((string) $request->path(), 0, 255),
                    'method' => mb_substr((string) $request->method(), 0, 10),
                    'status_code' => $statusCode,
                    'duration_ms' => max(0, $durationMs),
                    'user_id' => $userId,
                    'role' => is_object($user) ? (($user->role ?? null) ?: null) : null,
                    'error_class' => $errorClass,
                    'created_at' => now(),
                ]);
            } catch (Throwable $ignored) {
                // Never block API responses on metrics persistence errors.
            }
        }
    }
}
