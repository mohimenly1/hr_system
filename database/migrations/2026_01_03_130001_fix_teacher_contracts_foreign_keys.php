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
        // إصلاح المفاتيح الأجنبية لجدول teacher_contracts
        if (Schema::hasTable('teacher_contracts')) {
            Schema::table('teacher_contracts', function (Blueprint $table) {
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
                    // التحقق من وجود العمود
                    if (Schema::hasColumn('teacher_contracts', 'teacher_id')) {
                        // حذف السجلات التي لا تحتوي على teacher_id صالح
                        DB::statement("
                            DELETE tc FROM teacher_contracts tc
                            LEFT JOIN teachers t ON tc.teacher_id = t.id
                            WHERE t.id IS NULL
                        ");

                        // إضافة المفتاح الأجنبي
                        try {
                            $table->foreign('teacher_id', 'fk_teacher_contracts_teacher')
                                  ->references('id')
                                  ->on('teachers')
                                  ->onDelete('cascade');
                        } catch (\Exception $e) {
                            // إذا فشل، قد يكون بسبب نوع البيانات غير متطابق
                            // نحاول تعديل نوع البيانات أولاً
                            DB::statement("ALTER TABLE teacher_contracts MODIFY teacher_id BIGINT UNSIGNED NOT NULL");
                            $table->foreign('teacher_id', 'fk_teacher_contracts_teacher')
                                  ->references('id')
                                  ->on('teachers')
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
        if (Schema::hasTable('teacher_contracts')) {
            Schema::table('teacher_contracts', function (Blueprint $table) {
                $table->dropForeign('fk_teacher_contracts_teacher');
            });
        }
    }
};
