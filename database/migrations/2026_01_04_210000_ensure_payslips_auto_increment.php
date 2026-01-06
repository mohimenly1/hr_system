<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // Step 1: Check for any existing AUTO_INCREMENT columns
            $autoIncrementColumns = DB::select("
                SELECT COLUMN_NAME, EXTRA
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'payslips'
                AND EXTRA LIKE '%auto_increment%'
            ");

            // Step 2: Remove AUTO_INCREMENT from any column that is not 'id'
            foreach ($autoIncrementColumns as $col) {
                if ($col->COLUMN_NAME !== 'id') {
                    try {
                        $columnType = DB::select("
                            SELECT COLUMN_TYPE
                            FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = 'payslips'
                            AND COLUMN_NAME = ?
                        ", [$col->COLUMN_NAME]);

                        if (!empty($columnType)) {
                            $type = $columnType[0]->COLUMN_TYPE;
                            // Remove AUTO_INCREMENT from the type
                            $typeWithoutAuto = preg_replace('/\s+AUTO_INCREMENT/i', '', $type);
                            DB::statement("ALTER TABLE payslips MODIFY {$col->COLUMN_NAME} {$typeWithoutAuto}");
                        }
                    } catch (\Exception $e) {
                        Log::warning("Could not remove AUTO_INCREMENT from {$col->COLUMN_NAME}: " . $e->getMessage());
                    }
                }
            }

            // Step 3: Ensure PRIMARY KEY exists on id column
            $hasPrimaryKey = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'payslips'
                AND CONSTRAINT_TYPE = 'PRIMARY KEY'
            ");

            if (($hasPrimaryKey[0]->count ?? 0) == 0) {
                DB::statement('ALTER TABLE payslips ADD PRIMARY KEY (id)');
            }

            // Step 4: Get current max ID to set AUTO_INCREMENT properly
            $maxIdResult = DB::select("SELECT COALESCE(MAX(id), 0) as max_id FROM payslips");
            $maxId = $maxIdResult[0]->max_id ?? 0;
            $nextId = $maxId + 1;

            // Step 5: Modify id column to have AUTO_INCREMENT
            // Note: PRIMARY KEY should already exist from Step 3, so we don't add it again here
            DB::statement('ALTER TABLE payslips MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

            // Step 6: Set AUTO_INCREMENT starting point
            if ($nextId > 1) {
                DB::statement("ALTER TABLE payslips AUTO_INCREMENT = {$nextId}");
            }
        } catch (\Exception $e) {
            Log::warning('Could not set AUTO_INCREMENT for payslips.id: ' . $e->getMessage());
            // Try alternative approach: ensure PRIMARY KEY first, then add AUTO_INCREMENT
            try {
                // Ensure PRIMARY KEY exists
                try {
                    DB::statement('ALTER TABLE payslips ADD PRIMARY KEY (id)');
                } catch (\Exception $pkError) {
                    // Primary key might already exist
                }

                // Now add AUTO_INCREMENT
                DB::statement('ALTER TABLE payslips MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            } catch (\Exception $e2) {
                Log::error('Failed to fix AUTO_INCREMENT for payslips.id: ' . $e2->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a fix migration, no need to reverse
    }
};
