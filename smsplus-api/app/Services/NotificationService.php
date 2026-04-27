<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function notifyAll(array $data): void
    {
        $userIds = DB::table('ra_t_users')
            ->where('actif', true)
            ->pluck('id')
            ->all();

        $this->insertNotifications($userIds, $data);
    }

    public function notifyRole(string $role, array $data): void
    {
        $userIds = DB::table('ra_t_users')
            ->where('role', $role)
            ->where('actif', true)
            ->pluck('id')
            ->all();

        $this->insertNotifications($userIds, $data);
    }

    public function notifyRoles(array $roles, array $data): void
    {
        $userIds = DB::table('ra_t_users')
            ->whereIn('role', $roles)
            ->where('actif', true)
            ->pluck('id')
            ->all();

        $this->insertNotifications($userIds, $data);
    }

    public function notifyUser(int $userId, array $data): void
    {
        $this->insertNotifications([$userId], $data);
    }

    public function notifyAnomaly(float $ecartPct, string $date): void
    {
        $payload = [
            'type' => 'anomalie',
            'titre' => '⚠ Anomalie detectee',
            'message' => "Ecart MMG/OCC de {$ecartPct}% sur {$date}",
            'priorite' => 'critique',
            'icon' => 'anomalie',
            'action_url' => '/dashboard',
            'data' => ['ecart_pct' => $ecartPct, 'date' => $date],
        ];

        $this->notifyRoles(['ADMIN', 'ANALYSTE_OP'], $payload);
    }

    public function notifyImportDone(int $userId, int $lines): void
    {
        $this->notifyUser($userId, [
            'type' => 'import',
            'titre' => '✓ Import termine',
            'message' => "{$lines} lignes importees avec succes",
            'priorite' => 'normale',
            'icon' => 'import',
            'action_url' => '/imports',
            'data' => ['lines' => $lines],
        ]);
    }

    public function notifyFraudAlert(string $keyword, string $motif): void
    {
        $this->notifyRoles(['ADMIN', 'ANALYSTE_OP'], [
            'type' => 'alerte',
            'titre' => '🚨 Nouvelle alerte fraude',
            'message' => "Service {$keyword} : {$motif}",
            'priorite' => 'haute',
            'icon' => 'alerte',
            'action_url' => '/alerts',
            'data' => ['keyword' => $keyword, 'motif' => $motif],
        ]);
    }

    public function notifyReportReady(int $userId, string $mois): void
    {
        $this->notifyUser($userId, [
            'type' => 'rapport',
            'titre' => '📄 Rapport pret',
            'message' => "Rapport {$mois} disponible",
            'priorite' => 'normale',
            'icon' => 'rapport',
            'action_url' => '/revenus',
            'data' => ['mois' => $mois],
        ]);
    }

    public function notifyRevenueDrop(float $pct): void
    {
        $this->notifyRoles(['ADMIN', 'ANALYSTE_BUSS'], [
            'type' => 'systeme',
            'titre' => '📉 Baisse revenus detectee',
            'message' => "Revenus {$pct}% sous la moyenne",
            'priorite' => 'haute',
            'icon' => 'systeme',
            'action_url' => '/revenus',
            'data' => ['drop_pct' => $pct],
        ]);
    }

    private function insertNotifications(array $userIds, array $data): void
    {
        if ($userIds === []) {
            return;
        }

        $now = now();
        $type = $data['type'] ?? 'systeme';
        $priorite = $data['priorite'] ?? 'normale';

        foreach ($userIds as $userId) {
            Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'titre' => $data['titre'] ?? 'Notification',
                'message' => $data['message'] ?? '',
                'data' => $data['data'] ?? null,
                'lue' => false,
                'lue_at' => null,
                'priorite' => $priorite,
                'icon' => $data['icon'] ?? $type,
                'action_url' => $data['action_url'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
