<?php

namespace Modules\TicketManagement\Database\Seeders;

use Illuminate\Database\Seeder;

class TicketManagementDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            TicketManagementSeeder::class,
        ]);
    }
}
