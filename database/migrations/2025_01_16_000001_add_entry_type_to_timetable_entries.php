<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            // إضافة حقل نوع الإدخال
            if (!Schema::hasColumn('timetable_entries', 'entry_type')) {
                $table->enum('entry_type', [
                    'work',           // دوام عمل
                    'break',          // فترة راحة
                    'breakfast',      // استراحة فطور
                    'meeting',         // اجتماع
                    'workshop',        // ورشة عمل
                    'training',        // تدريب
                    'other'            // أخرى
                ])->default('work')->after('is_break')
                    ->comment('نوع الإدخال: دوام عمل، فترة راحة، استراحة فطور، اجتماع، ورشة عمل، تدريب، أخرى');
            }

            // إضافة حقل العنوان/الوصف للإدخال
            if (!Schema::hasColumn('timetable_entries', 'title')) {
                $table->string('title')->nullable()->after('entry_type')
                    ->comment('عنوان أو وصف الإدخال (مثل: اجتماع إدارة، ورشة عمل برمجة)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropColumn(['entry_type', 'title']);
        });
    }
};

