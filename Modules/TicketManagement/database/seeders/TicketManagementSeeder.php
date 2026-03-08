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
            ['name' => 'Backlog', 'slug' => 'backlog', 'color' => '#94A3B8', 'category' => 'todo', 'position' => 0, 'is_default' => false],
            ['name' => 'To Do', 'slug' => 'todo', 'color' => '#6B7280', 'category' => 'todo', 'position' => 1, 'is_default' => true],
            ['name' => 'In Progress', 'slug' => 'in_progress', 'color' => '#3B82F6', 'category' => 'in_progress', 'position' => 2, 'is_default' => false],
            ['name' => 'Review', 'slug' => 'review', 'color' => '#F59E0B', 'category' => 'in_progress', 'position' => 3, 'is_default' => false],
            ['name' => 'Done', 'slug' => 'done', 'color' => '#10B981', 'category' => 'done', 'position' => 4, 'is_default' => false],
            ['name' => 'Blocked', 'slug' => 'blocked', 'color' => '#EF4444', 'category' => 'todo', 'position' => 5, 'is_default' => false],
        ];

        foreach ($statuses as $status) {
            Status::updateOrCreate(
                ['project_id' => null, 'slug' => $status['slug']],
                [
                    'name' => $status['name'],
                    'color' => $status['color'],
                    'category' => $status['category'],
                    'position' => $status['position'],
                    'is_default' => $status['is_default'],
                ]
            );
        }

        // Backwards-compat: migrate old in_review slug to review
        $oldInReview = Status::where('project_id', null)->where('slug', 'in_review')->first();
        if ($oldInReview) {
            $existingReview = Status::where('project_id', null)->where('slug', 'review')->first();
            if ($existingReview) {
                $oldInReview->delete();
            } else {
                $oldInReview->update([
                    'name' => 'Review',
                    'slug' => 'review',
                    'category' => 'in_progress',
                ]);
            }
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
                Label::updateOrCreate(
                    ['project_id' => $firstProject->id, 'name' => $label['name']],
                    ['color' => $label['color']]
                );
            }
        }

        $this->command->info('Ticket management default data seeded successfully!');
    }
}
