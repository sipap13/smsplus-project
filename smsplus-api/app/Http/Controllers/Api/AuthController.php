<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\TwoFactorMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    private const MAX_2FA_ATTEMPTS = 3;

    private const BLOCK_DURATION_MINUTES = 15;

    private const RESEND_MAX_PER_HOUR = 3;

    private const CODE_LENGTH = 6;

    private const CODE_TTL_MINUTES = 10;

    /* ───────────────────────────────
       Helper : générer un code aléatoire
       ─────────────────────────────── */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /* ───────────────────────────────
       Helper : masquer l'email
       ─────────────────────────────── */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return substr($email, 0, 2).'***';
        }
        $name = $parts[0];
        $domain = $parts[1];
        $visible = max(1, (int) ceil(strlen($name) * 0.2));

        return substr($name, 0, $visible).str_repeat('*', max(1, strlen($name) - $visible)).'@'.$domain;
    }

    /* ───────────────────────────────
       Helper : logger une tentative
       ─────────────────────────────── */
    private function logAttempt(?object $user, string $status, Request $request, string $details = ''): void
    {
        DB::table('ra_t_login_logs')->insert([
            'user_id' => $user?->id,
            'email' => $user?->email ?? $request->input('email'),
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 512),
            'status' => $status,
            'details' => $details,
            'created_at' => now(),
        ]);
    }

    /* ───────────────────────────────
       Helper : clé de blocage/cache
       ─────────────────────────────── */
    private function blockKey(string $email): string
    {
        return '2fa_block_'.md5($email);
    }

    private function attemptsKey(string $email): string
    {
        return '2fa_attempts_'.md5($email);
    }

    private function resendKey(string $email): string
    {
        return '2fa_resend_'.md5($email);
    }

    private function rateLimitKey(Request $request): string
    {
        return 'login_rate_'.md5($request->ip());
    }

    /* ───────────────────────────────
       Helper : envoi SMS (placeholder)
       ─────────────────────────────── */
    private function sendSms(string $phone, string $code): bool
    {
        // TODO: Intégrer Twilio / SMS Provider tunisien
        // Pour l'instant on log simule l'envoi
        Log::info("[SMS 2FA] Code {$code} envoyé au {$phone}");

        return true;
    }

    /* ───────────────────────────────
       ÉTAPE 1 — login()
       ─────────────────────────────── */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Rate limiting par IP (5 tentatives / heure)
        $rateKey = $this->rateLimitKey($request);
        if (Cache::has($rateKey) && Cache::get($rateKey) >= 5) {
            return response()->json(['message' => 'Trop de tentatives. Réessayez dans une heure.'], 429);
        }

        $user = DB::table('ra_t_users')
            ->where('email', $request->email)
            ->where('actif', true)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            Cache::increment($rateKey);
            Cache::put($rateKey, Cache::get($rateKey, 1), now()->addHour());
            $this->logAttempt($user ?? null, 'failed_credentials', $request, 'Email ou mot de passe incorrect');

            return response()->json(['message' => 'Email ou mot de passe incorrect'], 401);
        }

        // Si 2FA désactivé pour cet utilisateur → connexion directe (fallback)
        if (! $user->two_fa_enabled) {
            return $this->issueTokenAndRespond($user, $request);
        }

        // Vérifier si l'utilisateur est bloqué
        if (Cache::has($this->blockKey($user->email))) {
            $remaining = Cache::get($this->blockKey($user->email));
            $minutes = now()->diffInMinutes($remaining, false);
            $minutes = abs((int) $minutes);

            return response()->json([
                'message' => "Trop de tentatives, réessayez dans {$minutes} min",
            ], 429);
        }

        // Déterminer la méthode 2FA
        $method = $user->two_fa_method ?? 'email';
        $hasPhone = ! empty($user->numero_personnel);

        // Si méthode SMS mais pas de téléphone → fallback email
        if (($method === 'sms' || $method === 'both') && ! $hasPhone) {
            $method = 'email';
        }

        // Générer le code
        $code = $this->generateCode();

        DB::table('ra_t_users')->where('id', $user->id)->update([
            'two_fa_code' => $code,
            'two_fa_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'updated_at' => now(),
        ]);

        // Envoi email
        if ($method === 'email' || $method === 'both') {
            try {
                Mail::to($user->email)->send(new TwoFactorMail($code));
            } catch (\Exception $e) {
                Log::error('Erreur envoi email 2FA: '.$e->getMessage());

                return response()->json(['message' => 'Erreur lors de l\'envoi du code. Réessayez.'], 500);
            }
        }

        // Envoi SMS (log pour l'instant)
        if ($method === 'sms' || $method === 'both') {
            $this->sendSms($user->numero_personnel, $code);
        }

        // Reset compteurs de tentatives
        Cache::forget($this->attemptsKey($user->email));

        $this->logAttempt($user, 'resend', $request, "Code 2FA envoyé par {$method}");

        // Préparer la réponse avec choix de méthode
        $response = [
            'step' => 'two_fa_required',
            'email' => $this->maskEmail($user->email),
            'expires_in' => self::CODE_TTL_MINUTES * 60,
            'message' => 'Code envoyé à votre email',
            'method' => $method,
        ];

        if ($hasPhone && ($method === 'email' || $method === 'sms')) {
            $response['available_methods'] = ['email', 'sms'];
            $response['phone'] = $this->maskPhone($user->numero_personnel);
        }

        return response()->json($response);
    }

    /* ───────────────────────────────
       ÉTAPE 2 — verifyTwoFa()
       ─────────────────────────────── */
    public function verifyTwoFa(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $email = $request->email;
        $code = $request->code;

        // Vérifier blocage
        if (Cache::has($this->blockKey($email))) {
            $remaining = Cache::get($this->blockKey($email));
            $minutes = abs((int) now()->diffInMinutes($remaining, false));

            return response()->json([
                'message' => "Trop de tentatives, réessayez dans {$minutes} min",
            ], 429);
        }

        $user = DB::table('ra_t_users')
            ->where('email', $email)
            ->where('actif', true)
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }

        // Vérifier expiration
        if (empty($user->two_fa_code) || empty($user->two_fa_expires_at) || now()->gt($user->two_fa_expires_at)) {
            $this->logAttempt($user, 'failed_2fa', $request, 'Code expiré');

            return response()->json([
                'message' => 'Code expiré, demandez un nouveau code',
                'expired' => true,
            ], 401);
        }

        // Vérifier le code
        if (! hash_equals((string) $user->two_fa_code, $code)) {
            $attemptsKey = $this->attemptsKey($email);
            $attempts = Cache::increment($attemptsKey);
            Cache::put($attemptsKey, $attempts, now()->addMinutes(self::BLOCK_DURATION_MINUTES));

            $remaining = self::MAX_2FA_ATTEMPTS - $attempts;

            $this->logAttempt($user, 'failed_2fa', $request, "Code invalide — tentative {$attempts}/".self::MAX_2FA_ATTEMPTS);

            if ($attempts >= self::MAX_2FA_ATTEMPTS) {
                Cache::put($this->blockKey($email), now()->addMinutes(self::BLOCK_DURATION_MINUTES), now()->addMinutes(self::BLOCK_DURATION_MINUTES));
                Cache::forget($attemptsKey);

                return response()->json([
                    'message' => 'Trop de tentatives, réessayez dans '.self::BLOCK_DURATION_MINUTES.' min',
                    'blocked' => true,
                    'blocked_until_minutes' => self::BLOCK_DURATION_MINUTES,
                ], 429);
            }

            return response()->json([
                'message' => "Code invalide, {$remaining} tentatives restantes",
                'attempts_remaining' => $remaining,
            ], 401);
        }

        // Code valide → reset et connexion
        Cache::forget($this->attemptsKey($email));
        Cache::forget($this->blockKey($email));

        return $this->issueTokenAndRespond($user, $request);
    }

    /* ───────────────────────────────
       RENVOI — resendCode()
       ─────────────────────────────── */
    public function resendCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;
        $resendKey = $this->resendKey($email);

        $count = Cache::get($resendKey, 0);
        if ($count >= self::RESEND_MAX_PER_HOUR) {
            return response()->json([
                'message' => 'Limite de renvois atteinte (3 par heure). Réessayez plus tard.',
            ], 429);
        }

        $user = DB::table('ra_t_users')
            ->where('email', $email)
            ->where('actif', true)
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }

        $method = $user->two_fa_method ?? 'email';
        $hasPhone = ! empty($user->numero_personnel);
        if (($method === 'sms' || $method === 'both') && ! $hasPhone) {
            $method = 'email';
        }

        $code = $this->generateCode();

        DB::table('ra_t_users')->where('id', $user->id)->update([
            'two_fa_code' => $code,
            'two_fa_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'updated_at' => now(),
        ]);

        if ($method === 'email' || $method === 'both') {
            try {
                Mail::to($user->email)->send(new TwoFactorMail($code));
            } catch (\Exception $e) {
                Log::error('Erreur renvoi email 2FA: '.$e->getMessage());

                return response()->json(['message' => 'Erreur lors de l\'envoi du code'], 500);
            }
        }

        if ($method === 'sms' || $method === 'both') {
            $this->sendSms($user->numero_personnel, $code);
        }

        Cache::increment($resendKey);
        Cache::put($resendKey, Cache::get($resendKey, 1), now()->addHour());

        // Reset compteurs de blocage
        Cache::forget($this->attemptsKey($email));

        $this->logAttempt($user, 'resend', $request, "Code 2FA renvoyé par {$method}");

        $response = [
            'email' => $this->maskEmail($user->email),
            'expires_in' => self::CODE_TTL_MINUTES * 60,
            'message' => 'Nouveau code envoyé',
            'method' => $method,
        ];

        if ($hasPhone && ($method === 'email' || $method === 'sms')) {
            $response['phone'] = $this->maskPhone($user->numero_personnel);
        }

        return response()->json($response);
    }

    /* ───────────────────────────────
       Helper : émettre token et répondre
       ─────────────────────────────── */
    private function issueTokenAndRespond(object $user, Request $request)
    {
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        DB::table('ra_t_api_tokens')->insert([
            'user_id' => $user->id,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ra_t_users')->where('id', $user->id)->update([
            'two_fa_code' => null,
            'two_fa_expires_at' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'updated_at' => now(),
        ]);

        $this->logAttempt($user, 'success', $request, 'Connexion réussie');

        return response()->json([
            'token' => $plainToken,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'direction' => $user->direction,
            ],
        ]);
    }

    /* ───────────────────────────────
       Helper : masquer le téléphone
       ─────────────────────────────── */
    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 4) {
            return '***'.substr($digits, -2);
        }

        return substr($digits, 0, 2).str_repeat('*', strlen($digits) - 4).substr($digits, -2);
    }

    /* ───────────────────────────────
       logout()
       ─────────────────────────────── */
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

    /* ───────────────────────────────
       me()
       ─────────────────────────────── */
    public function me(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        if (! $user) {
            return response()->json(['message' => 'Non autorisé'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'direction' => $user->direction,
        ]);
    }
}
