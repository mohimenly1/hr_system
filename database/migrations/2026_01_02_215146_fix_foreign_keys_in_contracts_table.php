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
        // التحقق من وجود الجدول
        if (!Schema::hasTable('contracts')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            // التحقق من وجود Foreign Key constraint
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'contracts'
                AND COLUMN_NAME = 'employee_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            // إذا لم يكن هناك Foreign Key، نضيفه
            if (empty($foreignKeys)) {
                // تنظيف البيانات غير الصالحة أولاً
                DB::statement("
                    DELETE c FROM contracts c
                    LEFT JOIN employees e ON c.employee_id = e.id
                    WHERE e.id IS NULL AND c.employee_id IS NOT NULL
                ");

                // التأكد من أن نوع العمود متطابق
                DB::statement("ALTER TABLE contracts MODIFY employee_id BIGINT UNSIGNED NOT NULL");

                // إضافة Foreign Key constraint
                $table->foreign('employee_id', 'fk_contracts_employee')
                      ->references('id')
                      ->on('employees')
                      ->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // إزالة Foreign Key constraint
            $table->dropForeign('fk_contracts_employee');
        });
    }
};
