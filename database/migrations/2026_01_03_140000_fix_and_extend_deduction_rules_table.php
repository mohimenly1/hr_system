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
        // حذف migration المسجل خطأً في قاعدة البيانات إذا كان موجوداً
        try {
            DB::table('migrations')
                ->where('migration', '2026_01_02_215146_extend_deduction_rules_for_daily_and_hourly_deductions')
                ->delete();
        } catch (\Exception $e) {
            // تجاهل الخطأ إذا لم يكن موجوداً
        }

        if (!Schema::hasTable('deduction_rules')) {
            return;
        }

        Schema::table('deduction_rules', function (Blueprint $table) {
            // إضافة حقل عدد الأيام للخصم (لنوع daily_salary)
            if (!Schema::hasColumn('deduction_rules', 'deduction_days')) {
                $table->unsignedInteger('deduction_days')->nullable()->after('deduction_amount')
                      ->comment('عدد الأيام للخصم من المرتب (لنوع daily_salary)');
            }

            // إضافة حقل عدد الساعات للخصم (لنوع hourly_salary)
            if (!Schema::hasColumn('deduction_rules', 'deduction_hours')) {
                $table->decimal('deduction_hours', 8, 2)->nullable()->after('deduction_days')
                      ->comment('عدد الساعات للخصم من المرتب (لنوع hourly_salary)');
            }
        });

        // تحديث enum في deduction_type لدعم الأنواع الجديدة
        // ملاحظة: Laravel لا يدعم تعديل enum مباشرة، لذا سنستخدم DB::statement
        try {
            // أولاً، نتحقق من القيم الحالية
            $currentValues = DB::select("SHOW COLUMNS FROM deduction_rules WHERE Field = 'deduction_type'");

            if (!empty($currentValues)) {
                // تحديث القيم غير المتوافقة إلى NULL
                DB::statement("UPDATE deduction_rules SET deduction_type = NULL WHERE deduction_type NOT IN ('fixed', 'percentage', 'daily_salary', 'hourly_salary')");

                // تحديث enum
                DB::statement("ALTER TABLE deduction_rules MODIFY COLUMN deduction_type ENUM('fixed', 'percentage', 'daily_salary', 'hourly_salary') NULL");
            }
        } catch (\Exception $e) {
            // إذا فشل التعديل، نحاول مرة أخرى بعد تنظيف البيانات
            try {
                DB::statement("UPDATE deduction_rules SET deduction_type = NULL WHERE deduction_type NOT IN ('fixed', 'percentage')");
                DB::statement("ALTER TABLE deduction_rules MODIFY COLUMN deduction_type ENUM('fixed', 'percentage', 'daily_salary', 'hourly_salary') NULL");
            } catch (\Exception $e2) {
                // إذا استمر الفشل، نتركه كما هو
                \Log::warning('Failed to update deduction_type enum: ' . $e2->getMessage());
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
                if (Schema::hasColumn('deduction_rules', 'deduction_hours')) {
                    $table->dropColumn('deduction_hours');
                }
                if (Schema::hasColumn('deduction_rules', 'deduction_days')) {
                    $table->dropColumn('deduction_days');
                }
            });

            // إعادة enum إلى القيم الأصلية
            try {
                DB::statement("UPDATE deduction_rules SET deduction_type = NULL WHERE deduction_type IN ('daily_salary', 'hourly_salary')");
                DB::statement("ALTER TABLE deduction_rules MODIFY COLUMN deduction_type ENUM('fixed', 'percentage') NULL");
            } catch (\Exception $e) {
                \Log::warning('Failed to revert deduction_type enum: ' . $e->getMessage());
            }
        }
    }
};
