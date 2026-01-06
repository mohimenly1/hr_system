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
            DB::statement("ALTER TABLE deduction_rules MODIFY COLUMN deduction_type ENUM('fixed', 'percentage', 'daily_salary', 'hourly_salary') NULL");
        } catch (\Exception $e) {
            // إذا فشل التعديل، قد يكون بسبب وجود بيانات غير متوافقة
            // سنحاول حذف القيم غير المتوافقة أولاً
            DB::statement("UPDATE deduction_rules SET deduction_type = NULL WHERE deduction_type NOT IN ('fixed', 'percentage', 'daily_salary', 'hourly_salary')");
            DB::statement("ALTER TABLE deduction_rules MODIFY COLUMN deduction_type ENUM('fixed', 'percentage', 'daily_salary', 'hourly_salary') NULL");
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
                DB::statement("ALTER TABLE deduction_rules MODIFY COLUMN deduction_type ENUM('fixed', 'percentage') NULL");
            } catch (\Exception $e) {
                // إذا فشل، نحذف القيم الجديدة أولاً
                DB::statement("UPDATE deduction_rules SET deduction_type = NULL WHERE deduction_type IN ('daily_salary', 'hourly_salary')");
                DB::statement("ALTER TABLE deduction_rules MODIFY COLUMN deduction_type ENUM('fixed', 'percentage') NULL");
            }
        }
    }
};
