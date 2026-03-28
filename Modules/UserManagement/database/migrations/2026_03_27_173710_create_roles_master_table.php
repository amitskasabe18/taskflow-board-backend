<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles_master', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g. Scrum Master
            $table->string('slug')->unique(); // e.g. scrum_master
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles_master');
    }
};
