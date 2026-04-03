<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportAlertsUsersFromExcel extends Command
{
    protected $signature = 'import:alerts-users
        {--path= : Chemin vers le fichier .xlsx (defaut: storage/imports/sms plus alerts + users.xlsx)}
        {--alerts-sheet=1 : Index de la feuille alertes (0 = 1ere feuille)}
        {--users-sheet=2 : Index de la feuille utilisateurs}
        {--skip-users : Ne pas importer les utilisateurs}
        {--fresh-alerts : Vider ra_t_alerts avant import (evite les doublons)}';

    protected $description = 'Importe alertes + utilisateurs depuis le fichier Excel « sms plus alerts + users.xlsx »';

    public function handle(): int
    {
        $relative = 'imports/sms plus alerts + users.xlsx';
        $path = $this->option('path') ?: storage_path($relative);

        if (! is_file($path)) {
            $this->error("Fichier introuvable: {$path}");

            return self::FAILURE;
        }

        $this->info("Lecture: {$path}");

        $sheets = Excel::toArray([], $path);
        $alertsSheet = (int) $this->option('alerts-sheet');
        $usersSheet = (int) $this->option('users-sheet');

        if (! isset($sheets[$alertsSheet])) {
            $this->error("Feuille alertes index {$alertsSheet} absente.");

            return self::FAILURE;
        }

        if ($this->option('fresh-alerts')) {
            DB::table('ra_t_alerts')->truncate();
            $this->warn('Table ra_t_alerts tronquee (--fresh-alerts).');
        }

        $aCount = $this->importAlerts($sheets[$alertsSheet]);
        $this->info("Alertes importees: {$aCount}");

        if (! $this->option('skip-users') && isset($sheets[$usersSheet])) {
            $uCount = $this->importUsers($sheets[$usersSheet]);
            $this->info("Utilisateurs importes / mis a jour: {$uCount}");
        } else {
            $this->warn('Import utilisateurs ignore (feuille vide ou --skip-users).');
        }

        return self::SUCCESS;
    }

    private function importAlerts(array $sheet): int
    {
        $headerRowIndex = null;
        $headers = [];

        foreach ($sheet as $ri => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $cell) {
                if (is_string($cell) && Str::contains(Str::lower($cell), 'id alert')) {
                    $headerRowIndex = $ri;
                    $headers = $this->normalizeHeaders($row);
                    break 2;
                }
            }
        }

        if ($headerRowIndex === null) {
            $this->warn('Aucune ligne d\'en-tete alerte trouvee (cherche "id alert").');

            return 0;
        }

        $map = $this->mapAlertColumns($headers);
        if (empty($map['start_date']) || empty($map['nom_service'])) {
            $this->warn('Colonnes alertes incompletes (besoin start date + nom service au minimum).');

            return 0;
        }

        $inserted = 0;
        for ($ri = $headerRowIndex + 1; $ri < count($sheet); $ri++) {
            $row = $sheet[$ri] ?? null;
            if (! is_array($row)) {
                continue;
            }

            $get = function (string $key) use ($row, $map): mixed {
                $idx = $map[$key] ?? null;
                if ($idx === null) {
                    return null;
                }

                return $row[$idx] ?? null;
            };

            $nomService = $this->cleanStr($get('nom_service'));
            if ($nomService === null) {
                continue;
            }

            $startDate = $this->parseExcelDate($get('start_date'));
            if ($startDate === null) {
                continue;
            }

            $status = $this->parseBool($get('status'));

            DB::table('ra_t_alerts')->insert([
                'start_date' => $startDate,
                'nom_service' => $nomService,
                'numero_court' => $this->cleanStr($get('numero_court')),
                'keyword' => $this->cleanStr($get('keyword')),
                'nom_fournisseur' => $this->cleanStr($get('nom_fournisseur')),
                'seuil_pct' => $this->parseDecimal($get('seuil_pct')),
                'count_nb_sms' => $this->parseInt($get('count_nb_sms')),
                'motif' => $this->cleanStr($get('motif')),
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $inserted++;
        }

        return $inserted;
    }

    /**
     * @return array<string, int|null> nom logique => index colonne
     */
    private function mapAlertColumns(array $headers): array
    {
        $out = [];
        foreach ($headers as $idx => $label) {
            if ($label === null || $label === '') {
                continue;
            }
            $l = Str::lower(trim((string) $label));
            $l = str_replace(['é', 'è', 'ê'], 'e', $l);

            if (str_contains($l, 'start date') || str_contains($l, 'date')) {
                $out['start_date'] = $idx;
            } elseif (str_contains($l, 'nom du service') || ($l === 'nom service')) {
                $out['nom_service'] = $idx;
            } elseif ($l === 'sc' || str_contains($l, 'numero') || str_contains($l, 'court')) {
                $out['numero_court'] = $idx;
            } elseif (str_contains($l, 'keyword')) {
                $out['keyword'] = $idx;
            } elseif (str_contains($l, 'fournisseur')) {
                $out['nom_fournisseur'] = $idx;
            } elseif (str_contains($l, 'augmentation') || str_contains($l, '20%') || str_contains($l, 'seuil')) {
                $out['seuil_pct'] = $idx;
            } elseif (str_contains($l, 'count') || str_contains($l, 'sms')) {
                $out['count_nb_sms'] = $idx;
            } elseif (str_contains($l, 'motif')) {
                $out['motif'] = $idx;
            } elseif (str_contains($l, 'status')) {
                $out['status'] = $idx;
            }
        }

        return $out;
    }

    private function importUsers(array $sheet): int
    {
        $horizontal = $this->importUsersHorizontal($sheet);
        if ($horizontal > 0) {
            return $horizontal;
        }

        $vertical = $this->importUsersVertical($sheet);
        if ($vertical > 0) {
            return $vertical;
        }

        $this->warn('Utilisateurs: aucun format reconnu. Utilise soit L1 = email|password|... avec lignes dessous, soit colonne A = nom du champ et colonne B = valeur sur chaque ligne.');

        return 0;
    }

    private function importUsersHorizontal(array $sheet): int
    {
        $headerRowIndex = null;
        foreach ($sheet as $ri => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $cell) {
                if (is_string($cell) && Str::lower(trim($cell)) === 'email') {
                    $headerRowIndex = $ri;
                    break 2;
                }
            }
        }

        if ($headerRowIndex === null) {
            return 0;
        }

        $headers = $this->normalizeHeaders($sheet[$headerRowIndex]);
        $map = [];
        foreach ($headers as $idx => $h) {
            if ($h === null) {
                continue;
            }
            $k = Str::lower(trim((string) $h));
            if ($k === 'email') {
                $map['email'] = $idx;
            }
            if (str_contains($k, 'password') || $k === 'mot de passe') {
                $map['password'] = $idx;
            }
            if (str_contains($k, 'direction')) {
                $map['direction'] = $idx;
            }
            if ($k === 'role') {
                $map['role'] = $idx;
            }
            if ($k === 'tel' || str_contains($k, 'telephone')) {
                $map['tel'] = $idx;
            }
        }

        if (! isset($map['email'], $map['password'])) {
            return 0;
        }

        $n = 0;
        for ($ri = $headerRowIndex + 1; $ri < count($sheet); $ri++) {
            $row = $sheet[$ri] ?? null;
            if (! is_array($row)) {
                continue;
            }
            $email = $this->cleanStr($row[$map['email']] ?? null);
            $password = $row[$map['password']] ?? null;
            if ($email === null || $password === null || $password === '') {
                continue;
            }

            $roleRaw = isset($map['role']) ? $this->cleanStr($row[$map['role']] ?? null) : 'ANALYSTE_OP';
            $direction = isset($map['direction']) ? $this->cleanStr($row[$map['direction']] ?? null) : 'Assurance et Fraude';
            $tel = isset($map['tel']) ? $this->cleanStr($row[$map['tel']] ?? null) : null;

            $this->persistUserRow($email, (string) $password, $roleRaw, $direction, $tel);
            $n++;
        }

        return $n;
    }

    /**
     * Colonne A = libelle (email, password, ...), colonne B = valeur.
     * Plusieurs utilisateurs : bloc complet puis ligne vide ou nouvelle cle email en debut de bloc.
     */
    private function importUsersVertical(array $sheet): int
    {
        $block = [];
        $n = 0;

        $flush = function () use (&$block, &$n): void {
            $em = $block['email'] ?? null;
            $pw = $block['password'] ?? null;
            if ($em !== null && $em !== '' && $pw !== null && $pw !== '') {
                $this->persistUserRow(
                    $em,
                    (string) $pw,
                    $block['role'] ?? null,
                    $block['direction'] ?? null,
                    $block['tel'] ?? null
                );
                $n++;
            }
            $block = [];
        };

        foreach ($sheet as $row) {
            if (! is_array($row)) {
                continue;
            }
            $k0 = $this->cleanStr($row[0] ?? null);
            $v = $row[1] ?? null;
            $vStr = $v === null || $v === '' ? null : trim((string) $v);

            if ($k0 === null && ($vStr === null || $vStr === '')) {
                $flush();

                continue;
            }
            if ($k0 === null) {
                continue;
            }

            $lk = Str::lower($k0);
            if ($lk === 'id' || $lk === 'image') {
                continue;
            }

            if ($vStr === null || $vStr === '') {
                continue;
            }

            if ($lk === 'email' && isset($block['email']) && $block['email'] !== '') {
                $flush();
            }

            match (true) {
                $lk === 'email' => $block['email'] = $vStr,
                str_contains($lk, 'password') || str_contains($lk, 'mot de passe') => $block['password'] = $vStr,
                str_contains($lk, 'direction') => $block['direction'] = $vStr,
                $lk === 'role' => $block['role'] = $vStr,
                $lk === 'tel' || str_contains($lk, 'telephone') => $block['tel'] = $vStr,
                default => null,
            };
        }

        $flush();

        return $n;
    }

    private function persistUserRow(string $email, string $passwordPlain, ?string $roleRaw, ?string $direction, ?string $tel): void
    {
        $role = $this->normalizeRole($roleRaw);
        $dir = $direction ?: 'Assurance et Fraude';

        $payload = [
            'email' => $email,
            'password' => Hash::make($passwordPlain),
            'direction' => $dir,
            'role' => $role,
            'tel' => $tel,
            'actif' => true,
            'updated_at' => now(),
        ];

        $exists = DB::table('ra_t_users')->where('email', $email)->exists();
        if ($exists) {
            DB::table('ra_t_users')->where('email', $email)->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::table('ra_t_users')->insert($payload);
        }
    }

    private function normalizeHeaders(array $row): array
    {
        $out = [];
        foreach ($row as $i => $v) {
            $out[$i] = is_string($v) ? trim($v) : $v;
        }

        return $out;
    }

    private function parseExcelDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
        $s = trim((string) $value);
        if ($s === '' || str_starts_with($s, '=')) {
            return Carbon::today()->toDateString();
        }
        try {
            return Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseBool(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        $s = Str::lower(trim((string) $value));

        return str_contains($s, 'true') || str_contains($s, 'vrai') || str_contains($s, 'resolu') || $s === '1';
    }

    private function parseDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $s = str_replace(',', '.', trim((string) $value));

        return is_numeric($s) ? (float) $s : null;
    }

    private function parseInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function cleanStr(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '' || $s === '_N') {
            return null;
        }

        return $s;
    }

    private function normalizeRole(?string $role): string
    {
        $r = Str::upper(trim((string) $role));
        if (in_array($r, ['ADMIN', 'ANALYSTE_OP', 'ANALYSTE_BUSS'], true)) {
            return $r;
        }

        return 'ANALYSTE_OP';
    }
}
