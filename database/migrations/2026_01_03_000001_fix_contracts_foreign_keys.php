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
            // التحقق من وجود بيانات غير صالحة قبل إضافة المفتاح الأجنبي
            $invalidRecords = DB::select("
                SELECT c.id, c.employee_id
                FROM contracts c
                LEFT JOIN employees e ON c.employee_id = e.id
                WHERE e.id IS NULL
            ");

            if (!empty($invalidRecords)) {
                // حذف السجلات غير الصالحة
                DB::statement("
                    DELETE c FROM contracts c
                    LEFT JOIN employees e ON c.employee_id = e.id
                    WHERE e.id IS NULL
                ");
            }

            // التأكد من أن العمود من النوع الصحيح
            Schema::table('contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('employee_id')->change();
            });

            // إضافة المفتاح الأجنبي
            Schema::table('contracts', function (Blueprint $table) {
                $table->foreign('employee_id', 'fk_contracts_employee_id')
                      ->references('id')
                      ->on('employees')
                      ->onDelete('cascade');
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
                $table->dropForeign('fk_contracts_employee_id');
            });
        }
    }
};
