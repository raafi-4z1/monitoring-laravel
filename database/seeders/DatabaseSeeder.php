<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MasterMetrikSeeder::class,
            ReportSourceSeeder::class,
            ResourcePermissionSeeder::class,
        ]);
    }
}
