<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // إضافة الأعمدة أولاً
        Schema::table('timetable_entries', function (Blueprint $table) {
            // ربط الجدول بالوردية - بدون foreign key constraint أولاً
            if (!Schema::hasColumn('timetable_entries', 'shift_id')) {
                $table->unsignedBigInteger('shift_id')->nullable()->after('section_id');
            }

            // نوع العمل (monthly_full, monthly_partial, hourly)
            if (!Schema::hasColumn('timetable_entries', 'work_type')) {
                $table->enum('work_type', ['monthly_full', 'monthly_partial', 'hourly'])
                    ->nullable()->after('shift_id')
                    ->comment('نوع العمل: شهري كامل، شهري جزئي، بالساعات');
            }

            // ساعات العمل الفعلية (بالدقائق)
            if (!Schema::hasColumn('timetable_entries', 'work_minutes')) {
                $table->integer('work_minutes')->nullable()->after('work_type')
                    ->comment('عدد دقائق العمل الفعلية لهذا الإدخال');
            }

            // فترة راحة (اختياري)
            if (!Schema::hasColumn('timetable_entries', 'is_break')) {
                $table->boolean('is_break')->default(false)->after('work_minutes')
                    ->comment('هل هذه فترة راحة؟');
            }

            // ترتيب الإدخال في اليوم (للمساعدة في العرض)
            if (!Schema::hasColumn('timetable_entries', 'order_in_day')) {
                $table->integer('order_in_day')->default(0)->after('is_break')
                    ->comment('ترتيب هذا الإدخال في اليوم');
            }
        });

        // إضافة foreign key constraint بشكل منفصل بعد التأكد من وجود جدول shifts
        if (Schema::hasTable('shifts') && Schema::hasColumn('timetable_entries', 'shift_id')) {
            // التحقق من وجود الـ constraint أولاً
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = 'timetable_entries'
                AND CONSTRAINT_NAME = 'timetable_entries_shift_id_foreign'
            ");

            if (empty($constraints)) {
                try {
                    Schema::table('timetable_entries', function (Blueprint $table) {
                        $table->foreign('shift_id')
                            ->references('id')
                            ->on('shifts')
                            ->onDelete('set null');
                    });
                } catch (\Exception $e) {
                    // إذا فشل، تجاهل الخطأ (قد يكون هناك مشكلة في قاعدة البيانات)
                    Log::warning('Failed to add foreign key constraint: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            // حذف foreign key constraint أولاً
            if (Schema::hasColumn('timetable_entries', 'shift_id')) {
                $table->dropForeign(['shift_id']);
            }
            $table->dropColumn(['shift_id', 'work_type', 'work_minutes', 'is_break', 'order_in_day']);
        });
    }
};
