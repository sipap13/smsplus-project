<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CdrMmgExport implements FromCollection, WithHeadings
{
    private $startDate;

    private $eventStatus;

    private $subscriberType;

    public function __construct($startDate = null, $eventStatus = null, $subscriberType = null)
    {
        $this->startDate = $startDate;
        $this->eventStatus = $eventStatus;
        $this->subscriberType = $subscriberType;
    }

    public function collection()
    {
        $query = DB::table('ra_t_mmg_cdr_det')
            ->select(
                'ne',
                'a_msisdn',
                'b_msisdn',
                'start_date',
                'start_hour',
                'event_type',
                'event_status',
                'subscriber_type',
                'service_type'
            );

        if ($this->startDate) {
            $query->where('start_date', '=', $this->startDate);
        }

        if ($this->eventStatus) {
            if (strcasecmp($this->eventStatus, 'Success') === 0) {
                $query->whereRaw("LOWER(TRIM(COALESCE(event_status, ''))) = 'success'");
            } elseif (strcasecmp($this->eventStatus, 'Failed') === 0) {
                $query->where(function ($w) {
                    $w->whereRaw("LOWER(TRIM(COALESCE(event_status, ''))) = 'failed'")
                      ->orWhereRaw("LOWER(TRIM(COALESCE(event_status, ''))) LIKE '%fail%'");
                });
            } else {
                $query->where('event_status', '=', $this->eventStatus);
            }
        }

        if ($this->subscriberType) {
            $query->where('subscriber_type', '=', $this->subscriberType);
        }

        return $query
            ->orderByDesc('start_date')
            ->limit(10000)
            ->get()
            ->map(function ($row) {
                return [
                    $row->ne,
                    $row->a_msisdn,
                    $row->b_msisdn,
                    $row->start_date,
                    $row->start_hour,
                    $row->event_type,
                    $row->event_status,
                    $row->subscriber_type,
                    $row->service_type,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'NE',
            'MSISDN Appelant',
            'MSISDN Destinataire',
            'Date',
            'Heure',
            'Type Événement',
            'Statut',
            'Type Abonné',
            'Type Service',
        ];
    }
}
