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

        // أولاً: تحديث enum deduction_type لدعم الأنواع الجديدة
        // نستخدم DB::statement مباشرة لأن Laravel لا يدعم تعديل enum بسهولة
        try {
            // التحقق من نوع العمود الحالي
            $columnInfo = DB::select("SHOW COLUMNS FROM deduction_rules WHERE Field = 'deduction_type'");

            if (!empty($columnInfo)) {
                $columnType = $columnInfo[0]->Type;

                // إذا كان enum، نعدله مباشرة
                if (strpos($columnType, 'enum') !== false) {
                    DB::statement("ALTER TABLE deduction_rules MODIFY COLUMN deduction_type ENUM('fixed', 'percentage', 'daily_salary', 'hourly_salary') NULL");
                } else {
                    // إذا كان string، نعدله ليدعم القيم الجديدة
                    DB::statement("ALTER TABLE deduction_rules MODIFY COLUMN deduction_type VARCHAR(50) NULL");
                }
            }
        } catch (\Exception $e) {
            // إذا فشل، نحاول حذف القيم غير المتوافقة أولاً
            DB::statement("UPDATE deduction_rules SET deduction_type = NULL WHERE deduction_type NOT IN ('fixed', 'percentage', 'daily_salary', 'hourly_salary')");
            try {
                DB::statement("ALTER TABLE deduction_rules MODIFY COLUMN deduction_type ENUM('fixed', 'percentage', 'daily_salary', 'hourly_salary') NULL");
            } catch (\Exception $e2) {
                // إذا فشل مرة أخرى، نستخدم VARCHAR
                DB::statement("ALTER TABLE deduction_rules MODIFY COLUMN deduction_type VARCHAR(50) NULL");
            }
        }

        // ثانياً: إضافة الأعمدة الجديدة إذا لم تكن موجودة
        Schema::table('deduction_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('deduction_rules', 'deduction_days')) {
                $table->unsignedInteger('deduction_days')->nullable()->after('deduction_amount')
                      ->comment('عدد الأيام للخصم من المرتب (لنوع daily_salary)');
            }

            if (!Schema::hasColumn('deduction_rules', 'deduction_hours')) {
                $table->decimal('deduction_hours', 8, 2)->nullable()->after('deduction_days')
                      ->comment('عدد الساعات للخصم من المرتب (لنوع hourly_salary)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('deduction_rules')) {
            return;
        }

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
            // حذف القيم الجديدة أولاً
            DB::statement("UPDATE deduction_rules SET deduction_type = NULL WHERE deduction_type IN ('daily_salary', 'hourly_salary')");
            DB::statement("ALTER TABLE deduction_rules MODIFY COLUMN deduction_type ENUM('fixed', 'percentage') NULL");
        } catch (\Exception $e) {
            // إذا فشل، نستخدم VARCHAR ونضيف constraint
            DB::statement("ALTER TABLE deduction_rules MODIFY COLUMN deduction_type VARCHAR(50) NULL");
        }
    }
};
