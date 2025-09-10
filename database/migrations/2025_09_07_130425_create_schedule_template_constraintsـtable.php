<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_template_constraints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_template_id')->constrained()->onDelete('cascade');
            $table->string('constraint_type')->comment('نوع القيد');
            $table->json('value')->comment('قيمة القيد');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_template_constraints');
    }
};
