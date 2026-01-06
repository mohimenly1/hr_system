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
            if (!Schema::hasColumn('timetable_entries', 'activity_type')) {
                $table->string('activity_type')->nullable()->after('is_break')
                    ->comment('نوع النشاط: break, breakfast, meeting, workshop, etc.');
            }
            if (!Schema::hasColumn('timetable_entries', 'activity_name')) {
                $table->string('activity_name')->nullable()->after('activity_type')
                    ->comment('اسم النشاط المخصص');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            if (Schema::hasColumn('timetable_entries', 'activity_name')) {
                $table->dropColumn('activity_name');
            }
            if (Schema::hasColumn('timetable_entries', 'activity_type')) {
                $table->dropColumn('activity_type');
            }
        });
    }
};
