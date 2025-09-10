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
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->onDelete('cascade');
            
            // علاقة متعددة الأشكال لربط الدوام بالموظف أو المعلم
            $table->morphs('shiftable'); // ستنشئ shiftable_id و shiftable_type

            $table->timestamps();

            // قيد فريد لضمان أن كل موظف/معلم لديه دوام واحد فقط
            $table->unique(['shiftable_id', 'shiftable_type'], 'employee_shift_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
