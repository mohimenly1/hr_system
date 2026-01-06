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
        Schema::table('deduction_rules', function (Blueprint $table) {
            // جعل deduction_type و deduction_amount nullable
            // إذا كانت null، سيتم استخدام القيم من PenaltyType المرتبط
            $table->enum('deduction_type', ['fixed', 'percentage'])->nullable()->change();
            $table->decimal('deduction_amount', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deduction_rules', function (Blueprint $table) {
            // إرجاع الحقول إلى required (غير nullable)
            // ملاحظة: قد تفشل إذا كانت هناك سجلات تحتوي على null
            $table->enum('deduction_type', ['fixed', 'percentage'])->nullable(false)->change();
            $table->decimal('deduction_amount', 10, 2)->nullable(false)->change();
        });
    }
};
