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
            try {
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
                    // التأكد من أن employee_id موجود في جدول employees
                    $employeesExists = DB::select("
                        SELECT COUNT(*) as count
                        FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'employees'
                    ");

                    if (!empty($employeesExists) && $employeesExists[0]->count > 0) {
                        // تنظيف البيانات غير الصالحة قبل إضافة المفتاح الأجنبي
                        DB::statement("
                            DELETE c FROM contracts c
                            LEFT JOIN employees e ON c.employee_id = e.id
                            WHERE e.id IS NULL
                        ");

                        // تعديل نوع العمود ليتطابق مع employees.id
                        DB::statement("
                            ALTER TABLE contracts
                            MODIFY employee_id BIGINT UNSIGNED NOT NULL
                        ");

                        // إضافة المفتاح الأجنبي
                        Schema::table('contracts', function (Blueprint $table) {
                            $table->foreign('employee_id', 'fk_contracts_employee')
                                  ->references('id')
                                  ->on('employees')
                                  ->onDelete('cascade');
                        });
                    }
                }
            } catch (\Exception $e) {
                // تسجيل الخطأ ولكن لا نوقف العملية
                \Log::warning('Failed to add foreign key to contracts table: ' . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('contracts')) {
            try {
                Schema::table('contracts', function (Blueprint $table) {
                    $table->dropForeign('fk_contracts_employee');
                });
            } catch (\Exception $e) {
                // تجاهل الخطأ إذا لم يكن المفتاح موجوداً
            }
        }
    }
};
