<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ServicesExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return DB::table('ra_t_services')
            ->select(
                'nom_fournisseur',
                'nom_service',
                'numero_court',
                'keyword',
                'type_service',
                'prix',
                'actif'
            )
            ->orderBy('nom_fournisseur')
            ->limit(10000)
            ->get()
            ->map(function ($row) {
                return [
                    $row->nom_fournisseur,
                    $row->nom_service,
                    $row->numero_court,
                    $row->keyword,
                    $row->type_service,
                    number_format($row->prix, 3, '.', ''),
                    $row->actif ? 'Actif' : 'Inactif',
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Fournisseur',
            'Nom Service',
            'Numéro Court',
            'Keyword',
            'Type',
            'Prix (DT)',
            'Statut',
        ];
    }
}
