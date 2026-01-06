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
        // إصلاح جدول scheduling_constraints لإضافة AUTO_INCREMENT
        try {
            // التحقق من وجود PRIMARY KEY
            $hasPrimaryKey = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = 'scheduling_constraints'
                AND CONSTRAINT_TYPE = 'PRIMARY KEY'
            ");

            if (isset($hasPrimaryKey[0]) && $hasPrimaryKey[0]->count == 0) {
                // إضافة PRIMARY KEY إذا لم يكن موجوداً
                DB::statement('ALTER TABLE `scheduling_constraints` ADD PRIMARY KEY (`id`)');
            }

            // إضافة AUTO_INCREMENT
            DB::statement('ALTER TABLE `scheduling_constraints` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

            // تعيين AUTO_INCREMENT إلى القيمة التالية بعد آخر ID
            $maxId = DB::table('scheduling_constraints')->max('id') ?? 0;
            if ($maxId > 0) {
                DB::statement("ALTER TABLE `scheduling_constraints` AUTO_INCREMENT = " . ($maxId + 1));
            }

        } catch (\Exception $e) {
            // إذا فشل، تجاهل الخطأ (قد يكون موجوداً بالفعل)
            \Log::warning('Failed to fix scheduling_constraints table: ' . $e->getMessage());
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
