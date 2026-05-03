<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Log an action to the audit log table.
     */
    public function log(
        string $action,
        string $entite,
        string $description,
        array $avant = [],
        array $apres = [],
        string $statut = 'succes',
        ?string $entiteId = null
    ): void {
        $user = request()->attributes->get('auth_user');

        AuditLog::create([
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'user_role' => $user?->role,
            'action' => $action,
            'entite' => $entite,
            'entite_id' => $entiteId,
            'description' => $description,
            'donnees_avant' => $avant,
            'donnees_apres' => $apres,
            'ip_address' => request()->ip(),
            'user_agent' => substr(request()->userAgent() ?? '', 0, 500),
            'statut' => $statut,
            'created_at' => now(),
        ]);
    }
}
