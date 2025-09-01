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
        Schema::table('attachments', function (Blueprint $table) {
            // Drop the old foreign key
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');

            // Add polymorphic columns
            $table->morphs('attachable'); // This will add `attachable_id` (unsignedBigInteger) and `attachable_type` (string)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropMorphs('attachable');
            
            // Re-add the old column if we need to roll back
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
        });
    }
};
