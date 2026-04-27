<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CdrOccExport implements FromCollection, WithHeadings
{
    private $startDate;

    private $keyword;

    private $subscriberType;

    private $partner;

    public function __construct($startDate = null, $keyword = null, $subscriberType = null, $partner = null)
    {
        $this->startDate = $startDate;
        $this->keyword = $keyword;
        $this->subscriberType = $subscriberType;
        $this->partner = $partner;
    }

    public function collection()
    {
        $query = DB::table('ra_t_occ_cdr_detail')
            ->select(
                'a_msisdn',
                'b_msisdn',
                'start_date',
                'start_hour',
                'call_type',
                'event_type',
                'subscriber_type',
                'roaming_type',
                'partner',
                'charge_amount',
                'keyword'
            );

        if ($this->startDate) {
            $query->where('start_date', '=', $this->startDate);
        }

        if ($this->keyword) {
            $query->where('keyword', '=', $this->keyword);
        }

        if ($this->subscriberType) {
            $query->where('subscriber_type', '=', $this->subscriberType);
        }

        if ($this->partner) {
            $query->where('partner', 'like', '%'.$this->partner.'%');
        }

        return $query
            ->orderByDesc('start_date')
            ->limit(10000)
            ->get()
            ->map(function ($row) {
                return [
                    $row->a_msisdn,
                    $row->b_msisdn,
                    $row->start_date,
                    $row->start_hour,
                    $row->call_type,
                    $row->event_type,
                    $row->subscriber_type,
                    $row->roaming_type,
                    $row->partner,
                    number_format($row->charge_amount, 3, '.', ''),
                    $row->keyword,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'MSISDN Appelant',
            'MSISDN Destinataire',
            'Date',
            'Heure',
            'Type Appel',
            'Type Événement',
            'Type Abonné',
            'Itinérance',
            'Partenaire',
            'Montant (DT)',
            'Keyword',
        ];
    }
}
