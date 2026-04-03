<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RaTUsersSeeder::class,
            RaTServicesSeeder::class,
            RaTOccCdrSeeder::class,
            RaTPfeCdrSeeder::class,
        ]);
    }
}