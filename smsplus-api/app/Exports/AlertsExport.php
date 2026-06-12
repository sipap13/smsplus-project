<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AlertsExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return DB::table('ra_t_alerts')
            ->select(
                'created_at',
                'nom_service',
                'numero_court',
                'keyword',
                'nom_fournisseur',
                'seuil_pct',
                'count_nb_sms',
                'motif',
                'status'
            )
            ->orderByDesc('created_at')
            ->limit(10000)
            ->get()
            ->map(function ($row) {
                return [
                    $row->created_at,
                    $row->nom_service,
                    $row->numero_court,
                    $row->keyword,
                    $row->nom_fournisseur,
                    number_format($row->seuil_pct, 2, '.', '').'%',
                    $row->count_nb_sms,
                    $row->motif,
                    $row->status ? 'Actif' : 'Inactif',
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Date',
            'Service',
            'Numéro Court',
            'Keyword',
            'Fournisseur',
            'Seuil %',
            'Nb SMS',
            'Motif',
            'Statut',
        ];
    }
}
