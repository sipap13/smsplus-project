<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RaTUsersSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('ra_t_users')->insert([
            [
                'email'     => 'admin@tt.tn',
                'password'  => Hash::make('admin123'),
                'direction' => 'Assurance et Fraude',
                'role'      => 'ADMIN',
                'tel'       => '+216 71 000 001',
                'actif'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'email'     => 'analyste.op@tt.tn',
                'password'  => Hash::make('op123'),
                'direction' => 'Assurance et Fraude',
                'role'      => 'ANALYSTE_OP',
                'tel'       => '+216 71 000 002',
                'actif'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'email'     => 'analyste.buss@tt.tn',
                'password'  => Hash::make('buss123'),
                'direction' => 'Assurance et Fraude',
                'role'      => 'ANALYSTE_BUSS',
                'tel'       => '+216 71 000 003',
                'actif'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}