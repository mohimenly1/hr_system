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
        // Step 1: Make employee_id nullable
        Schema::table('payslips', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->change();
        });

        // Step 2: Add teacher columns without foreign keys first
        // Check the actual data type of referenced columns first
        $teachersIdType = DB::select("
            SELECT DATA_TYPE, COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'teachers'
            AND COLUMN_NAME = 'id'
        ");

        $teacherContractsIdType = DB::select("
            SELECT DATA_TYPE, COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'teacher_contracts'
            AND COLUMN_NAME = 'id'
        ");

        Schema::table('payslips', function (Blueprint $table) use ($teachersIdType, $teacherContractsIdType) {
            if (!Schema::hasColumn('payslips', 'teacher_id')) {
                // Match the exact type from teachers.id
                if (!empty($teachersIdType)) {
                    $type = $teachersIdType[0]->COLUMN_TYPE;
                    if (strpos($type, 'unsigned') !== false) {
                        $table->unsignedBigInteger('teacher_id')->nullable()->after('employee_id');
                    } else {
                        $table->bigInteger('teacher_id')->nullable()->after('employee_id');
                    }
                } else {
                    $table->unsignedBigInteger('teacher_id')->nullable()->after('employee_id');
                }
            }
            if (!Schema::hasColumn('payslips', 'teacher_contract_id')) {
                // Match the exact type from teacher_contracts.id
                if (!empty($teacherContractsIdType)) {
                    $type = $teacherContractsIdType[0]->COLUMN_TYPE;
                    if (strpos($type, 'unsigned') !== false) {
                        $table->unsignedBigInteger('teacher_contract_id')->nullable()->after('contract_id');
                    } else {
                        $table->bigInteger('teacher_contract_id')->nullable()->after('contract_id');
                    }
                } else {
                    $table->unsignedBigInteger('teacher_contract_id')->nullable()->after('contract_id');
                }
            }
        });

        // Step 3: Drop old unique constraint if it exists
        try {
            Schema::table('payslips', function (Blueprint $table) {
                $table->dropUnique(['employee_id', 'month', 'year']);
            });
        } catch (\Exception $e) {
            // Constraint might not exist or have different name, try alternative
            try {
                DB::statement('ALTER TABLE payslips DROP INDEX payslips_employee_id_month_year_unique');
            } catch (\Exception $e2) {
                // Ignore if doesn't exist
            }
        }

        // Step 4: Add foreign keys (only if they don't exist)
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'payslips'
            AND CONSTRAINT_NAME LIKE '%teacher%'
        ");

        $existingForeignKeyNames = array_column($foreignKeys, 'CONSTRAINT_NAME');

        Schema::table('payslips', function (Blueprint $table) use ($existingForeignKeyNames) {
            if (!in_array('payslips_teacher_id_foreign', $existingForeignKeyNames) &&
                Schema::hasTable('teachers') &&
                Schema::hasColumn('payslips', 'teacher_id')) {
                try {
                    // Check for orphaned data before creating foreign key
                    $orphanedData = DB::select("
                        SELECT COUNT(*) as count
                        FROM payslips p
                        LEFT JOIN teachers t ON p.teacher_id = t.id
                        WHERE p.teacher_id IS NOT NULL
                        AND t.id IS NULL
                    ");

                    $orphanedCount = $orphanedData[0]->count ?? 0;

                    if ($orphanedCount > 0) {
                        Log::warning("Found {$orphanedCount} orphaned teacher_id records. Cleaning up...");
                        // Set orphaned records to NULL
                        DB::statement("
                            UPDATE payslips p
                            LEFT JOIN teachers t ON p.teacher_id = t.id
                            SET p.teacher_id = NULL
                            WHERE p.teacher_id IS NOT NULL
                            AND t.id IS NULL
                        ");
                    }

                    // Use raw SQL to create foreign key
                    DB::statement("
                        ALTER TABLE payslips
                        ADD CONSTRAINT payslips_teacher_id_foreign
                        FOREIGN KEY (teacher_id)
                        REFERENCES teachers(id)
                        ON DELETE CASCADE
                    ");
                } catch (\Exception $e) {
                    // Log but don't fail if foreign key already exists or can't be created
                    Log::warning('Could not create teacher_id foreign key: ' . $e->getMessage());
                }
            }
            if (!in_array('payslips_teacher_contract_id_foreign', $existingForeignKeyNames) &&
                Schema::hasTable('teacher_contracts') &&
                Schema::hasColumn('payslips', 'teacher_contract_id')) {
                try {
                    // Verify column types match before creating foreign key
                    $payslipColumnType = DB::select("
                        SELECT COLUMN_TYPE
                        FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'payslips'
                        AND COLUMN_NAME = 'teacher_contract_id'
                    ");

                    $contractColumnType = DB::select("
                        SELECT COLUMN_TYPE
                        FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'teacher_contracts'
                        AND COLUMN_NAME = 'id'
                    ");

                    // Only create foreign key if types match
                    if (!empty($payslipColumnType) && !empty($contractColumnType)) {
                        $payslipType = $payslipColumnType[0]->COLUMN_TYPE;
                        $contractType = $contractColumnType[0]->COLUMN_TYPE;

                        // Normalize types for comparison (remove length specifications)
                        $payslipTypeNormalized = preg_replace('/\([^)]*\)/', '', $payslipType);
                        $contractTypeNormalized = preg_replace('/\([^)]*\)/', '', $contractType);

                        // Check for orphaned data before creating foreign key
                        $orphanedData = DB::select("
                            SELECT COUNT(*) as count
                            FROM payslips p
                            LEFT JOIN teacher_contracts tc ON p.teacher_contract_id = tc.id
                            WHERE p.teacher_contract_id IS NOT NULL
                            AND tc.id IS NULL
                        ");

                        $orphanedCount = $orphanedData[0]->count ?? 0;

                        if ($orphanedCount > 0) {
                            Log::warning("Found {$orphanedCount} orphaned teacher_contract_id records. Cleaning up...");
                            // Set orphaned records to NULL
                            DB::statement("
                                UPDATE payslips p
                                LEFT JOIN teacher_contracts tc ON p.teacher_contract_id = tc.id
                                SET p.teacher_contract_id = NULL
                                WHERE p.teacher_contract_id IS NOT NULL
                                AND tc.id IS NULL
                            ");
                        }

                        if ($payslipTypeNormalized === $contractTypeNormalized ||
                            (strpos($payslipType, 'bigint') !== false && strpos($contractType, 'bigint') !== false)) {
                            // Use raw SQL to create foreign key with explicit error handling
                            DB::statement("
                                ALTER TABLE payslips
                                ADD CONSTRAINT payslips_teacher_contract_id_foreign
                                FOREIGN KEY (teacher_contract_id)
                                REFERENCES teacher_contracts(id)
                                ON DELETE CASCADE
                            ");
                        } else {
                            Log::warning("Column type mismatch: payslips.teacher_contract_id ({$payslipType}) vs teacher_contracts.id ({$contractType})");
                        }
                    }
                } catch (\Exception $e) {
                    // Log but don't fail if foreign key already exists or can't be created
                    Log::warning('Could not create teacher_contract_id foreign key: ' . $e->getMessage());
                    // Try to get more details about the error
                    try {
                        $checkData = DB::select("
                            SELECT p.teacher_contract_id, COUNT(*) as count
                            FROM payslips p
                            WHERE p.teacher_contract_id IS NOT NULL
                            GROUP BY p.teacher_contract_id
                        ");
                        Log::info('Sample teacher_contract_id values in payslips:', $checkData);
                    } catch (\Exception $e2) {
                        // Ignore
                    }
                }
            }
        });

        // Step 5: Add new unique constraints
        Schema::table('payslips', function (Blueprint $table) {
            $table->unique(['employee_id', 'month', 'year'], 'payslips_employee_unique');
            $table->unique(['teacher_id', 'month', 'year'], 'payslips_teacher_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropUnique('payslips_employee_unique');
            $table->dropUnique('payslips_teacher_unique');

            $table->dropForeign(['teacher_id']);
            $table->dropForeign(['teacher_contract_id']);
            $table->dropColumn(['teacher_id', 'teacher_contract_id']);

            $table->foreignId('employee_id')->nullable(false)->change();
            $table->unique(['employee_id', 'month', 'year']);
        });
    }
};
