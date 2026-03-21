<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert default ticket statuses
        $statuses = [
            [
                'name' => 'Backlog',
                'slug' => 'backlog',
                'color' => '#94A3B8',
                'category' => 'todo',
                'position' => 0,
                'is_default' => false,
                'project_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'To Do',
                'slug' => 'todo',
                'color' => '#6B7280',
                'category' => 'todo',
                'position' => 1,
                'is_default' => true,
                'project_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'In Progress',
                'slug' => 'in_progress',
                'color' => '#3B82F6',
                'category' => 'in_progress',
                'position' => 2,
                'is_default' => false,
                'project_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Review',
                'slug' => 'review',
                'color' => '#F59E0B',
                'category' => 'in_progress',
                'position' => 3,
                'is_default' => false,
                'project_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Done',
                'slug' => 'done',
                'color' => '#10B981',
                'category' => 'done',
                'position' => 4,
                'is_default' => false,
                'project_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Blocked',
                'slug' => 'blocked',
                'color' => '#EF4444',
                'category' => 'todo',
                'position' => 5,
                'is_default' => false,
                'project_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('statuses')->insertOrIgnore($statuses);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('statuses')
            ->whereIn('slug', ['backlog', 'todo', 'in_progress', 'review', 'done', 'blocked'])
            ->where('project_id', null)
            ->delete();
    }
};
