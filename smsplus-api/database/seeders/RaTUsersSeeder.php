<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RaTUsersSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'email'     => 'admin@tt.tn',
                'password'  => Hash::make('admin123'),
                'direction' => 'Assurance et Fraude',
                'role'      => 'ADMIN',
                'tel'       => '+216 71 000 001',
                'actif'     => true,
            ],
            [
                'email'     => 'analyste.op@tt.tn',
                'password'  => Hash::make('op123'),
                'direction' => 'Assurance et Fraude',
                'role'      => 'ANALYSTE_OP',
                'tel'       => '+216 71 000 002',
                'actif'     => true,
            ],
            [
                'email'     => 'analyste.buss@tt.tn',
                'password'  => Hash::make('buss123'),
                'direction' => 'Assurance et Fraude',
                'role'      => 'ANALYSTE_BUSS',
                'tel'       => '+216 71 000 003',
                'actif'     => true,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('ra_t_users')->updateOrInsert(
                ['email' => $row['email']],
                array_merge($row, [
                    'updated_at' => now(),
                    'created_at' => DB::raw("COALESCE(created_at, '" . now()->toDateTimeString() . "')"),
                ])
            );
        }
    }
}