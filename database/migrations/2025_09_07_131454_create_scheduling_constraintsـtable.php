<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduling_constraints', function (Blueprint $table) {
            $table->id();
            $table->morphs('schedulable'); // للموظف أو المعلم
            $table->string('constraint_type')->comment('نوع القيد، مثل: max_hours_per_week');
            $table->json('value')->comment('قيمة القيد، قد تكون رقم، نص، أو مصفوفة');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_constraints');
    }
};
