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
        Schema::table('scheduling_constraints', function (Blueprint $table) {
            // نوع التوظيف (monthly_full, monthly_partial, hourly)
            $table->enum('employment_type', ['monthly_full', 'monthly_partial', 'hourly'])
                ->nullable()->after('constraint_type')
                ->comment('نوع التوظيف: شهري كامل، شهري جزئي، بالساعات');

            // أيام العمل المحددة (JSON array)
            $table->json('specific_work_days')->nullable()->after('value')
                ->comment('أيام عمل محددة (مصفوفة JSON)');

            // فترات الراحة (JSON array)
            $table->json('break_periods')->nullable()->after('specific_work_days')
                ->comment('فترات الراحة (مصفوفة JSON)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduling_constraints', function (Blueprint $table) {
            $table->dropColumn(['employment_type', 'specific_work_days', 'break_periods']);
        });
    }
};
