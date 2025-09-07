<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_experiences', function (Blueprint $table) {
            // Drop the old foreign key constraint and column
            if (Schema::hasColumn('work_experiences', 'employee_id')) {
                // --- THIS IS THE FIX ---
                // First, we must drop the foreign key constraint.
                // Laravel can automatically find the constraint name if you pass the column name in an array.
                $table->dropForeign(['employee_id']);
                
                // Now, we can safely drop the column.
                $table->dropColumn('employee_id');
            }

            // Add the new polymorphic columns
            $table->morphs('experienceable'); // This will create `experienceable_id` and `experienceable_type`
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_experiences', function (Blueprint $table) {
            $table->dropMorphs('experienceable');
            $table->foreignId('employee_id')->nullable()->constrained()->onDelete('cascade');
        });
    }
};

