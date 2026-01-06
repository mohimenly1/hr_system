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
        Schema::create('payroll_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('total_amount', 12, 2)->default(0)->comment('إجمالي المبلغ المصروف');
            $table->integer('total_payslips')->default(0)->comment('عدد قسائم الراتب المصروفة');
            $table->integer('employees_count')->default(0)->comment('عدد الموظفين');
            $table->integer('teachers_count')->default(0)->comment('عدد المعلمين');
            $table->enum('status', ['draft', 'completed', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['month', 'year']);
            $table->index(['month', 'year', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_expenses');
    }
};
