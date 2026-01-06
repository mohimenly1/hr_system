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
        // إصلاح AUTO_INCREMENT لعمود id في جدول leaves
        // يجب أن يكون id PRIMARY KEY ليعمل AUTO_INCREMENT
        // نستخدم طريقة أكثر أماناً: أولاً نضيف PRIMARY KEY إذا لم يكن موجوداً، ثم نضيف AUTO_INCREMENT

        try {
            // محاولة إضافة PRIMARY KEY أولاً (إذا لم يكن موجوداً)
            DB::statement('ALTER TABLE `leaves` ADD PRIMARY KEY (`id`)');
        } catch (\Exception $e) {
            // تجاهل الخطأ إذا كان PRIMARY KEY موجوداً بالفعل
        }

        // الآن إضافة AUTO_INCREMENT
        DB::statement('ALTER TABLE `leaves` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // لا يمكن عكس هذا التغيير بشكل آمن، لذا نتركه فارغاً
        // أو يمكن إزالة AUTO_INCREMENT إذا لزم الأمر
        // DB::statement('ALTER TABLE `leaves` MODIFY `id` BIGINT UNSIGNED NOT NULL');
    }
};
