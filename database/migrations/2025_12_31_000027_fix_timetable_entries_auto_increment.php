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
        // إصلاح جدول timetable_entries لإضافة AUTO_INCREMENT
        try {
            // التحقق من وجود PRIMARY KEY
            $hasPrimaryKey = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = 'timetable_entries'
                AND CONSTRAINT_TYPE = 'PRIMARY KEY'
            ");

            if (isset($hasPrimaryKey[0]) && $hasPrimaryKey[0]->count == 0) {
                // إضافة PRIMARY KEY إذا لم يكن موجوداً
                DB::statement('ALTER TABLE `timetable_entries` ADD PRIMARY KEY (`id`)');
            }

            // إضافة AUTO_INCREMENT
            DB::statement('ALTER TABLE `timetable_entries` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

            // تعيين AUTO_INCREMENT إلى القيمة التالية بعد آخر ID
            $maxId = DB::table('timetable_entries')->max('id') ?? 0;
            if ($maxId > 0) {
                DB::statement("ALTER TABLE `timetable_entries` AUTO_INCREMENT = " . ($maxId + 1));
            }

        } catch (\Exception $e) {
            // إذا فشل، تجاهل الخطأ (قد يكون موجوداً بالفعل)
            \Log::warning('Failed to fix timetable_entries table: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // لا يمكن التراجع عن هذا التغيير بأمان
    }
};
