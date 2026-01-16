<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CliniqueSeeder::class,
            MedecinSeeder::class,
            PatientSeeder::class,
            AdminSeeder::class,
        ]);
    }
}
