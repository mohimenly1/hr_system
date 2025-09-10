<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('اسم القالب، مثل: دوام كامل للمعلمين');
            $table->text('description')->nullable()->comment('وصف موجز للقالب');
            $table->enum('type', ['teacher', 'employee', 'general'])->default('general')->comment('يحدد نوع القالب لتسهيل الفلترة');
            $table->boolean('is_active')->default(true)->comment('لتفعيل أو تعطيل القالب');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_templates');
    }
};

