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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');

            // Contract Details
            $table->string('contract_type')->comment('نوع العقد');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('probation_end_date')->nullable()->comment('نهاية الفترة التجريبية');
            $table->string('job_title');
            $table->enum('status', ['active', 'pending', 'expired', 'terminated'])->default('pending')->comment('حالة العقد');

            // Salary Details
            $table->decimal('basic_salary', 10, 2);
            $table->decimal('housing_allowance', 10, 2)->default(0);
            $table->decimal('transportation_allowance', 10, 2)->default(0);
            $table->decimal('other_allowances', 10, 2)->default(0);

            // Work Schedule & Leave
            $table->unsignedTinyInteger('working_hours_per_day')->default(8)->comment('ساعات العمل اليومية');
            $table->unsignedTinyInteger('annual_leave_days')->default(21)->comment('أيام الإجازة السنوية');
            $table->unsignedSmallInteger('notice_period_days')->default(30)->comment('فترة الإشعار باليوم');

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
