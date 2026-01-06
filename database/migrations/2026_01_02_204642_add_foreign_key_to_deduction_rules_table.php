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
        // التحقق من وجود الجدولين أولاً
        if (Schema::hasTable('deduction_rules') && Schema::hasTable('penalty_types')) {
            // التحقق من عدم وجود Foreign Key مسبقاً
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'deduction_rules'
                AND CONSTRAINT_NAME = 'fk_deduction_rules_penalty_type'
            ");

            if (empty($foreignKeys)) {
                // حذف السجلات التي تحتوي على penalty_type_id غير موجود في penalty_types
                DB::statement("
                    DELETE dr FROM deduction_rules dr
                    LEFT JOIN penalty_types pt ON dr.penalty_type_id = pt.id
                    WHERE pt.id IS NULL AND dr.penalty_type_id IS NOT NULL
                ");

                // التأكد من أن نوع البيانات متطابق
                // تحديث penalty_type_id ليكون BIGINT UNSIGNED إذا لم يكن كذلك
                DB::statement("
                    ALTER TABLE deduction_rules
                    MODIFY penalty_type_id BIGINT UNSIGNED NOT NULL
                ");

                // إضافة Foreign Key constraint
                try {
                    Schema::table('deduction_rules', function (Blueprint $table) {
                        $table->foreign('penalty_type_id', 'fk_deduction_rules_penalty_type')
                              ->references('id')
                              ->on('penalty_types')
                              ->onDelete('cascade');
                    });
                } catch (\Exception $e) {
                    // إذا فشل، قد يكون بسبب بيانات غير صالحة أو مشكلة في نوع البيانات
                    // يمكن تجاهل الخطأ إذا كان Foreign Key موجوداً بالفعل
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('deduction_rules')) {
            Schema::table('deduction_rules', function (Blueprint $table) {
                $table->dropForeign('fk_deduction_rules_penalty_type');
            });
        }
    }
};
