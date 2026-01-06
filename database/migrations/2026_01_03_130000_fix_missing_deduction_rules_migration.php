<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // حذف migration المسجل خطأً في قاعدة البيانات إذا كان موجوداً
        DB::table('migrations')
            ->where('migration', '2026_01_02_215146_extend_deduction_rules_for_daily_and_hourly_deductions')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // لا يمكن إعادة السجل المحذوف
    }
};
