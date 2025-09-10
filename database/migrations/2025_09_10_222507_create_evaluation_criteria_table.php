<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('اسم معيار التقييم، مثل: جودة العمل');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('max_score')->default(10);
            $table->boolean('affects_salary')->default(false)->comment('هل يؤثر هذا المعيار على الراتب');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_criteria');
    }
};
