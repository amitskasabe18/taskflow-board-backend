<?php

namespace Modules\UserManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolesMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);

        $roles = [
            'Scrum Master',
            'UX Designer',
            'UI Designer',
            'UX Researcher',
            'Stakeholder',
            'Business Sponsor',
            'Automation Engineer',
            'DevOps Engineer',
            'Site Reliability Engineer (SRE)',
            'Security Engineer',
            'Data Engineer',
            'Data Analyst',
            'Machine Learning Engineer',
            'Technical Writer',
            'Release Manager',
            'Customer Support / Customer Success',
            'Other',
        ];

        foreach ($roles as $role) {
            DB::table('roles_master')->insert([
                'name' => $role,
                'slug' => Str::slug($role), // e.g. "automation-engineer"
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
