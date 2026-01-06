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
        if (!Schema::hasTable('teacher_contracts')) {
            return;
        }

        // التحقق من وجود المفتاح الأجنبي
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'teacher_contracts'
            AND COLUMN_NAME = 'teacher_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        if (empty($foreignKeys)) {
            // التحقق من وجود بيانات غير صالحة قبل إضافة المفتاح الأجنبي
            $invalidRecords = DB::select("
                SELECT tc.id, tc.teacher_id
                FROM teacher_contracts tc
                LEFT JOIN teachers t ON tc.teacher_id = t.id
                WHERE t.id IS NULL
            ");

            if (!empty($invalidRecords)) {
                // حذف السجلات غير الصالحة
                DB::statement("
                    DELETE tc FROM teacher_contracts tc
                    LEFT JOIN teachers t ON tc.teacher_id = t.id
                    WHERE t.id IS NULL
                ");
            }

            // التأكد من أن العمود من النوع الصحيح
            Schema::table('teacher_contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('teacher_id')->change();
            });

            // إضافة المفتاح الأجنبي
            Schema::table('teacher_contracts', function (Blueprint $table) {
                $table->foreign('teacher_id', 'fk_teacher_contracts_teacher_id')
                      ->references('id')
                      ->on('teachers')
                      ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('teacher_contracts')) {
            Schema::table('teacher_contracts', function (Blueprint $table) {
                $table->dropForeign('fk_teacher_contracts_teacher_id');
            });
        }
    }
};
