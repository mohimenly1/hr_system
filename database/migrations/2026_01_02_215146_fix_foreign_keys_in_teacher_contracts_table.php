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

        Schema::table('teacher_contracts', function (Blueprint $table) {
            // التحقق من وجود Foreign Key constraint
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'teacher_contracts'
                AND COLUMN_NAME = 'teacher_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            // إذا لم يكن هناك Foreign Key، نضيفه
            if (empty($foreignKeys)) {
                // تنظيف البيانات غير الصالحة أولاً
                DB::statement("
                    DELETE tc FROM teacher_contracts tc
                    LEFT JOIN teachers t ON tc.teacher_id = t.id
                    WHERE t.id IS NULL AND tc.teacher_id IS NOT NULL
                ");

                // التأكد من أن نوع العمود متطابق
                DB::statement("ALTER TABLE teacher_contracts MODIFY teacher_id BIGINT UNSIGNED NOT NULL");

                // إضافة Foreign Key constraint
                $table->foreign('teacher_id', 'fk_teacher_contracts_teacher')
                      ->references('id')
                      ->on('teachers')
                      ->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_contracts', function (Blueprint $table) {
            // إزالة Foreign Key constraint
            $table->dropForeign('fk_teacher_contracts_teacher');
        });
    }
};
