<?php

namespace Modules\TicketManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\TicketManagement\Entities\Status;
use Modules\TicketManagement\Entities\Label;

class TicketManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default statuses
        $statuses = [
            ['name' => 'To Do', 'slug' => 'todo', 'color' => '#6B7280', 'category' => 'todo', 'position' => 1, 'is_default' => true],
            ['name' => 'In Progress', 'slug' => 'in_progress', 'color' => '#3B82F6', 'category' => 'in_progress', 'position' => 2, 'is_default' => false],
            ['name' => 'In Review', 'slug' => 'in_review', 'color' => '#F59E0B', 'category' => 'in_progress', 'position' => 3, 'is_default' => false],
            ['name' => 'Done', 'slug' => 'done', 'color' => '#10B981', 'category' => 'done', 'position' => 4, 'is_default' => false],
            ['name' => 'Blocked', 'slug' => 'blocked', 'color' => '#EF4444', 'category' => 'todo', 'position' => 5, 'is_default' => false],
        ];

        foreach ($statuses as $status) {
            Status::firstOrCreate(
                ['slug' => $status['slug']],
                [
                    'name' => $status['name'],
                    'color' => $status['color'],
                    'category' => $status['category'],
                    'position' => $status['position'],
                    'is_default' => $status['is_default'],
                ]
            );
        }

        // Create default labels (project-specific labels)
        $labels = [
            ['name' => 'Bug', 'color' => '#EF4444'],
            ['name' => 'Feature', 'color' => '#3B82F6'],
            ['name' => 'Enhancement', 'color' => '#8B5CF6'],
            ['name' => 'Documentation', 'color' => '#06B6D4'],
            ['name' => 'Urgent', 'color' => '#DC2626'],
            ['name' => 'Performance', 'color' => '#F59E0B'],
            ['name' => 'Security', 'color' => '#7C3AED'],
            ['name' => 'Testing', 'color' => '#10B981'],
        ];

        // Get the first project ID or create labels for each project
        $firstProject = \DB::table('projects')->first();
        if ($firstProject) {
            foreach ($labels as $label) {
                \DB::table('labels')->insert([
                    'name' => $label['name'],
                    'color' => $label['color'],
                    'project_id' => $firstProject->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Ticket management default data seeded successfully!');
    }
}
