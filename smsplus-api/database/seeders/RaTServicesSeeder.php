<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RaTServicesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('ra_t_services')->insert([
            [
                'nom_fournisseur' => 'TOPNET',
                'nom_service'     => 'SHOFHA',
                'numero_court'    => '2168000',
                'keyword'         => 'mb1',
                'type_service'    => 'Service',
                'prix'            => 0.500,
                'actif'           => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'nom_fournisseur' => 'TOPNET',
                'nom_service'     => 'SHOFHA SE',
                'numero_court'    => '2168000',
                'keyword'         => 'se1',
                'type_service'    => 'Service',
                'prix'            => 0.500,
                'actif'           => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'nom_fournisseur' => 'TOPNET',
                'nom_service'     => 'PLAY WIN',
                'numero_court'    => '2168000',
                'keyword'         => 'plw1',
                'type_service'    => 'jeu',
                'prix'            => 0.500,
                'actif'           => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'nom_fournisseur' => 'TOPNET',
                'nom_service'     => 'MELODY',
                'numero_court'    => '2168000',
                'keyword'         => 'mel1',
                'type_service'    => 'Service',
                'prix'            => 0.500,
                'actif'           => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'nom_fournisseur' => 'TOPNET',
                'nom_service'     => 'OUK',
                'numero_court'    => '2168000',
                'keyword'         => 'ouk1',
                'type_service'    => 'Service',
                'prix'            => 0.500,
                'actif'           => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'nom_fournisseur' => 'TOPNET',
                'nom_service'     => 'BUSINESS',
                'numero_court'    => '2168000',
                'keyword'         => 'bus1',
                'type_service'    => 'Service',
                'prix'            => 0.500,
                'actif'           => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ]);
    }
}