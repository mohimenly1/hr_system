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
        Schema::create('custom_hours', function (Blueprint $table) {
            $table->id();

            // علاقة متعددة الأشكال لربط الساعات بالموظف أو المعلم
            $table->morphs('hourly');

            // اليوم (0 = الأحد, 6 = السبت)
            $table->unsignedTinyInteger('day_of_week')->comment('0=الأحد, 1=الإثنين, ..., 6=السبت');

            // الساعات المخصصة لهذا اليوم
            $table->decimal('hours', 5, 2)->comment('عدد الساعات لهذا اليوم');

            // وقت البداية (اختياري - إذا كان محدداً)
            $table->time('start_time')->nullable()->comment('وقت البداية المحدد');

            // وقت النهاية (اختياري - إذا كان محدداً)
            $table->time('end_time')->nullable()->comment('وقت النهاية المحدد');

            // ملاحظات
            $table->text('notes')->nullable()->comment('ملاحظات إضافية');

            $table->timestamps();

            // قيد فريد لضمان أن كل موظف/معلم لديه ساعة واحدة لكل يوم
            $table->unique(['hourly_id', 'hourly_type', 'day_of_week'], 'unique_hourly_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_hours');
    }
};
