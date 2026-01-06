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
        Schema::table('payslips', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('status')->comment('هل تم الصرف يدوياً بدون مراجعة الحضور');
            $table->boolean('deductions_overridden')->default(false)->after('is_manual')->comment('هل تم تجاوز القيود الخصمية');
            $table->text('override_reason')->nullable()->after('deductions_overridden')->comment('سبب تجاوز القيود');
            $table->decimal('total_earnings', 10, 2)->default(0)->after('gross_salary')->comment('إجمالي الإضافات');
            $table->foreignId('payroll_expense_id')->nullable()->after('notes')->constrained('payroll_expenses')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropForeign(['payroll_expense_id']);
            $table->dropColumn([
                'is_manual',
                'deductions_overridden',
                'override_reason',
                'total_earnings',
                'payroll_expense_id',
            ]);
        });
    }
};
