<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_evaluations', function (Blueprint $table) {
            $table->id();
            $table->morphs('evaluable'); // For Employee or Teacher
            $table->string('title')->comment('عنوان التقييم، مثل: تقييم الربع الأول 2025');
            $table->date('evaluation_date');
            $table->decimal('final_score_percentage', 5, 2)->nullable()->comment('النتيجة النهائية كنسبة مئوية');
            $table->text('overall_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_evaluations');
    }
};
