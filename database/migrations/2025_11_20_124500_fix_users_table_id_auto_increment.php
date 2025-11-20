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
        // Fix the id column to have AUTO_INCREMENT
        // This is a raw SQL query because Schema builder doesn't support modifying AUTO_INCREMENT directly
        DB::statement('ALTER TABLE `users` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We can't really reverse this without knowing the original state
        // But we'll keep the AUTO_INCREMENT as it should be the default
    }
};

