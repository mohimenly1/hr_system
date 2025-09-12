<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalty_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('اسم العقوبة، مثل: إنذار كتابي');
            $table->text('description')->nullable();
            $table->boolean('affects_evaluation')->default(false)->comment('هل تؤثر على التقييم');
            $table->boolean('affects_salary')->default(false)->comment('هل تؤثر على الراتب');
            $table->enum('deduction_type', ['fixed', 'percentage'])->nullable()->comment('نوع الخصم');
            $table->decimal('deduction_amount', 8, 2)->nullable()->comment('قيمة الخصم');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // --- THE FIX: Temporarily disable foreign key checks to allow rollback ---
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('penalty_types');
        Schema::enableForeignKeyConstraints();
    }
};

