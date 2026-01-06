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
        Schema::create('overtime_entries', function (Blueprint $table) {
            $table->id();
            $table->morphs('schedulable'); // schedulable_id, schedulable_type (Employee/Teacher)
            $table->date('date'); // تاريخ اليوم المحدد
            $table->time('start_time'); // وقت بداية الإضافي
            $table->time('end_time'); // وقت نهاية الإضافي
            $table->integer('minutes')->default(0); // عدد الدقائق الإضافية
            $table->text('notes')->nullable(); // ملاحظات
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending'); // حالة الموافقة
            $table->unsignedBigInteger('approved_by')->nullable(); // من وافق عليه
            $table->timestamp('approved_at')->nullable(); // تاريخ الموافقة
            $table->timestamps();

            // Indexes
            $table->index(['schedulable_id', 'schedulable_type', 'date']);
            $table->index('date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_entries');
    }
};
