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
        // إصلاح المفاتيح الأجنبية لجدول contracts
        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                // التحقق من وجود المفتاح الأجنبي
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'contracts'
                    AND COLUMN_NAME = 'employee_id'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");

                if (empty($foreignKeys)) {
                    // التحقق من وجود العمود
                    if (Schema::hasColumn('contracts', 'employee_id')) {
                        // حذف السجلات التي لا تحتوي على employee_id صالح
                        DB::statement("
                            DELETE c FROM contracts c
                            LEFT JOIN employees e ON c.employee_id = e.id
                            WHERE e.id IS NULL
                        ");

                        // إضافة المفتاح الأجنبي
                        try {
                            $table->foreign('employee_id', 'fk_contracts_employee')
                                  ->references('id')
                                  ->on('employees')
                                  ->onDelete('cascade');
                        } catch (\Exception $e) {
                            // إذا فشل، قد يكون بسبب نوع البيانات غير متطابق
                            // نحاول تعديل نوع البيانات أولاً
                            DB::statement("ALTER TABLE contracts MODIFY employee_id BIGINT UNSIGNED NOT NULL");
                            $table->foreign('employee_id', 'fk_contracts_employee')
                                  ->references('id')
                                  ->on('employees')
                                  ->onDelete('cascade');
                        }
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropForeign('fk_contracts_employee');
            });
        }
    }
};
