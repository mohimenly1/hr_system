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
        // Fix AUTO_INCREMENT for payslips table
        // First, ensure PRIMARY KEY exists
        try {
            DB::statement('ALTER TABLE payslips ADD PRIMARY KEY (id)');
        } catch (\Exception $e) {
            // Primary key might already exist, ignore
        }

        // Then, ensure AUTO_INCREMENT is enabled
        try {
            DB::statement('ALTER TABLE payslips MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        } catch (\Exception $e) {
            // If it fails, try to get current max ID and set AUTO_INCREMENT
            try {
                $maxId = DB::select("SELECT COALESCE(MAX(id), 0) as max_id FROM payslips");
                $nextId = ($maxId[0]->max_id ?? 0) + 1;
                DB::statement("ALTER TABLE payslips MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
                DB::statement("ALTER TABLE payslips AUTO_INCREMENT = {$nextId}");
            } catch (\Exception $e2) {
                // Log but don't fail
                \Log::warning('Could not set AUTO_INCREMENT for payslips.id: ' . $e2->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this fix
    }
};
