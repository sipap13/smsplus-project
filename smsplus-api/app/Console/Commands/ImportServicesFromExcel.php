<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportServicesFromExcel extends Command
{
    protected $signature = 'import:services
        {--path= : Chemin vers le fichier .xlsx (defaut: storage/imports/List VAS 1.xlsx)}
        {--sheet=0 : Index de la feuille (0 = 1ere feuille)}
        {--fresh : Vider ra_t_services avant import}';

    protected $description = 'Importe la liste des fournisseurs et services VAS depuis un fichier Excel';

    public function handle(): int
    {
        $relative = 'imports/List VAS 1.xlsx';
        $path = $this->option('path') ?: storage_path($relative);

        if (! is_file($path)) {
            $this->error("Fichier introuvable: {$path}");
            return self::FAILURE;
        }

        $this->info("Lecture: {$path}");

        $sheets = Excel::toArray([], $path);
        $sheetIndex = (int) $this->option('sheet');

        if (! isset($sheets[$sheetIndex])) {
            $this->error("Feuille index {$sheetIndex} absente.");
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            DB::table('ra_t_services')->truncate();
            $this->warn('Table ra_t_services tronquee (--fresh).');
        }

        $count = $this->importServices($sheets[$sheetIndex]);
        $this->info("Services importes / mis a jour: {$count}");

        return self::SUCCESS;
    }

    private function importServices(array $sheet): int
    {
        if (count($sheet) < 2) {
            $this->warn("Fichier vide ou sans donnees.");
            return 0;
        }

        $headers = $this->normalizeHeaders($sheet[0]);
        $map = $this->mapColumns($headers);

        if (!isset($map['nom_fournisseur']) || !isset($map['nom_service']) || !isset($map['keyword'])) {
            $this->error("Colonnes requises introuvables. Colonnes detectees : " . implode(', ', array_keys($map)));
            return 0;
        }

        $inserted = 0;
        for ($ri = 1; $ri < count($sheet); $ri++) {
            $row = $sheet[$ri] ?? null;
            if (! is_array($row)) {
                continue;
            }

            $get = function (string $key) use ($row, $map): mixed {
                $idx = $map[$key] ?? null;
                return $idx !== null ? ($row[$idx] ?? null) : null;
            };

            $nomFournisseur = $this->cleanStr($get('nom_fournisseur'));
            $nomService = $this->cleanStr($get('nom_service'));
            $keyword = strtoupper(trim((string) $get('keyword')));
            $numeroCourt = trim((string) $get('numero_court'));
            $type = $this->cleanStr($get('type_service'));
            $prix = $this->parseDecimal($get('prix'));

            if ($nomFournisseur === null && $nomService === null && $keyword === '') {
                continue;
            }

            if ($nomFournisseur === null) {
                $nomFournisseur = 'Autre';
            }
            if ($nomService === null) {
                $nomService = 'Autre';
            }
            if ($numeroCourt === '') {
                $numeroCourt = '0';
            }
            if ($keyword === '') {
                $keyword = '_N';
            }

            // Normalise le type_service par rapport à l'enum ('Service', 'jeu')
            if ($type !== null) {
                $typeLower = strtolower($type);
                if (str_contains($typeLower, 'jeu') || str_contains($typeLower, 'game')) {
                    $type = 'jeu';
                } else {
                    $type = 'Service';
                }
            }

            $payload = [
                'nom_fournisseur' => $nomFournisseur,
                'nom_service' => $nomService,
                'numero_court' => $numeroCourt,
                'keyword' => $keyword,
                'type_service' => $type,
                'prix' => $prix,
                'actif' => true,
                'updated_at' => now(),
            ];

            // Upsert based on keyword and numero_court
            $exists = DB::table('ra_t_services')
                ->where('keyword', $keyword)
                ->where('numero_court', $numeroCourt)
                ->first();

            if ($exists) {
                DB::table('ra_t_services')->where('id', $exists->id)->update($payload);
            } else {
                $payload['created_at'] = now();
                DB::table('ra_t_services')->insert($payload);
            }

            $inserted++;
        }

        return $inserted;
    }

    private function mapColumns(array $headers): array
    {
        $out = [];
        foreach ($headers as $idx => $label) {
            if ($label === null || $label === '') {
                continue;
            }
            $l = Str::lower(trim((string) $label));
            
            if (str_contains($l, 'fournisseur')) {
                $out['nom_fournisseur'] = $idx;
            } elseif (str_contains($l, 'service')) {
                $out['nom_service'] = $idx;
            } elseif (str_contains($l, 'court') || str_contains($l, 'sc')) {
                $out['numero_court'] = $idx;
            } elseif (str_contains($l, 'keyword')) {
                $out['keyword'] = $idx;
            } elseif (str_contains($l, 'type')) {
                $out['type_service'] = $idx;
            } elseif (str_contains($l, 'prix') || str_contains($l, 'ttc')) {
                $out['prix'] = $idx;
            }
        }
        return $out;
    }

    private function normalizeHeaders(array $row): array
    {
        $out = [];
        foreach ($row as $i => $v) {
            $out[$i] = is_string($v) ? trim($v) : $v;
        }
        return $out;
    }

    private function cleanStr(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        return $s;
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
}
