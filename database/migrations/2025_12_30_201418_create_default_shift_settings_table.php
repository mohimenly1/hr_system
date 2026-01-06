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
        Schema::create('default_shift_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('اسم الإعداد (مثل: الدوام الافتراضي للمؤسسة)');
            $table->time('start_time')->comment('وقت بداية الدوام الافتراضي');
            $table->time('end_time')->comment('وقت نهاية الدوام الافتراضي');
            $table->json('work_days')->comment('أيام العمل (مصفوفة JSON: [0,1,2,3,4] للأحد-الخميس)');
            $table->integer('hours_per_week')->comment('عدد ساعات العمل في الأسبوع');
            $table->integer('hours_per_month')->comment('عدد ساعات العمل في الشهر');
            $table->integer('work_days_per_week')->comment('عدد أيام العمل في الأسبوع');
            $table->boolean('is_active')->default(true)->comment('هل الإعداد نشط');
            $table->text('description')->nullable()->comment('وصف الإعداد');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('default_shift_settings');
    }
};
