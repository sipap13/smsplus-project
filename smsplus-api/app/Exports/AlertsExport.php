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
            ->leftJoin('ra_t_services', 'ra_t_services.id', '=', 'ra_t_alerts.service_id')
            ->select(
                'ra_t_alerts.created_at',
                'ra_t_services.nom_service',
                'ra_t_services.numero_court',
                'ra_t_services.keyword',
                'ra_t_services.nom_fournisseur',
                'ra_t_alerts.seuil_pct',
                'ra_t_alerts.count_nb_sms',
                'ra_t_alerts.motif',
                'ra_t_alerts.actif'
            )
            ->orderByDesc('ra_t_alerts.created_at')
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
                    $row->actif ? 'Actif' : 'Inactif',
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
