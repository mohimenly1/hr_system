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
        Schema::table('leaves', function (Blueprint $table) {
            // Drop the old foreign key constraint and column if they exist
            if (Schema::hasColumn('leaves', 'employee_id')) {
                $table->dropForeign(['employee_id']);
                $table->dropColumn('employee_id');
            }

            // Add the new polymorphic columns
            $table->morphs('leavable'); // This creates `leavable_id` and `leavable_type`
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropMorphs('leavable');
            $table->foreignId('employee_id')->nullable()->constrained()->onDelete('cascade');
        });
    }
};
