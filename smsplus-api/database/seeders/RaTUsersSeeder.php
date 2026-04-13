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
                'nom'       => 'Administrateur',
                'numero_personnel' => 'USR-001',
                'direction' => 'Assurance et Fraude',
                'role'      => 'ADMIN',
                'tel'       => '+216 71 000 001',
                'actif'     => true,
            ],
            [
                'email'     => 'analyste.op@tt.tn',
                'password'  => Hash::make('op123'),
                'nom'       => 'Analyste Operations',
                'numero_personnel' => 'USR-002',
                'direction' => 'Assurance et Fraude',
                'role'      => 'ANALYSTE_OP',
                'tel'       => '+216 71 000 002',
                'actif'     => true,
            ],
            [
                'email'     => 'analyste.buss@tt.tn',
                'password'  => Hash::make('buss123'),
                'nom'       => 'Analyste Business',
                'numero_personnel' => 'USR-003',
                'direction' => 'Assurance et Fraude',
                'role'      => 'ANALYSTE_BUSS',
                'tel'       => '+216 71 000 003',
                'actif'     => true,
            ],
        ];

        foreach ($rows as $row) {
            $existing = DB::table('ra_t_users')->where('email', $row['email'])->first();
            if ($existing) {
                DB::table('ra_t_users')
                    ->where('email', $row['email'])
                    ->update(array_merge($row, [
                        'updated_at' => now(),
                    ]));
            } else {
                DB::table('ra_t_users')
                    ->insert(array_merge($row, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
            }
        }
    }
}