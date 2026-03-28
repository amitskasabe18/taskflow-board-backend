<?php

namespace Modules\UserManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\UserManagement\Database\Seeders\RolesMasterSeeder;

class UserManagementDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        $this->call(RolesMasterSeeder::class);
    }
}
