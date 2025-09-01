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
        Schema::create('teacher_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->string('contract_type')->comment('e.g., Full-time, Part-time, Hourly');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            
            // Salary Details
            $table->enum('salary_type', ['monthly', 'hourly']);
            $table->decimal('salary_amount', 10, 2)->nullable()->comment('For monthly salary');
            $table->decimal('hourly_rate', 8, 2)->nullable()->comment('For hourly rate');

            // Workload
            $table->unsignedInteger('working_hours_per_week')->nullable();

            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'pending', 'expired', 'terminated'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_contracts');
    }
};
