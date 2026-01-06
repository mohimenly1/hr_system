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
            try {
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
                    // التأكد من أن teachers table موجود
                    $teachersExists = DB::select("
                        SELECT COUNT(*) as count
                        FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'teachers'
                    ");

                    if (!empty($teachersExists) && $teachersExists[0]->count > 0) {
                        // تنظيف البيانات غير الصالحة قبل إضافة المفتاح الأجنبي
                        DB::statement("
                            DELETE tc FROM teacher_contracts tc
                            LEFT JOIN teachers t ON tc.teacher_id = t.id
                            WHERE t.id IS NULL
                        ");

                        // تعديل نوع العمود ليتطابق مع teachers.id
                        DB::statement("
                            ALTER TABLE teacher_contracts
                            MODIFY teacher_id BIGINT UNSIGNED NOT NULL
                        ");

                        // إضافة المفتاح الأجنبي
                        Schema::table('teacher_contracts', function (Blueprint $table) {
                            $table->foreign('teacher_id', 'fk_teacher_contracts_teacher')
                                  ->references('id')
                                  ->on('teachers')
                                  ->onDelete('cascade');
                        });
                    }
                }
            } catch (\Exception $e) {
                // تسجيل الخطأ ولكن لا نوقف العملية
                \Log::warning('Failed to add foreign key to teacher_contracts table: ' . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('teacher_contracts')) {
            try {
                Schema::table('teacher_contracts', function (Blueprint $table) {
                    $table->dropForeign('fk_teacher_contracts_teacher');
                });
            } catch (\Exception $e) {
                // تجاهل الخطأ إذا لم يكن المفتاح موجوداً
            }
        }
    }
};
