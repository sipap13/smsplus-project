<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RaTReclamationsSeeder extends Seeder
{
    public function run(): void
    {
        $serviceId = DB::table('ra_t_services')->value('id');

        if (! $serviceId) {
            return;
        }

        $rows = [
            [
                'msisdn' => '21697537900',
                'service_id' => $serviceId,
                'description' => 'Abonnement non desire active automatiquement',
                'statut' => 'ouverte',
            ],
            [
                'msisdn' => '21697537900',
                'service_id' => $serviceId,
                'description' => 'Debit facture trop eleve sur service VAS',
                'statut' => 'en_cours',
            ],
            [
                'msisdn' => '21698542320',
                'service_id' => $serviceId,
                'description' => 'Demande de desactivation du service',
                'statut' => 'resolue',
            ],
            [
                'msisdn' => '21696776950',
                'service_id' => $serviceId,
                'description' => 'SMS de confirmation non recu',
                'statut' => 'ouverte',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('ra_t_reclamations')->updateOrInsert(
                [
                    'msisdn' => $row['msisdn'],
                    'description' => $row['description'],
                ],
                array_merge($row, [
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }
    }
}

