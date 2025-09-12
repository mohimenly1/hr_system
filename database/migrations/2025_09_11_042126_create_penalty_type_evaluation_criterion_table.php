<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalty_type_evaluation_criterion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penalty_type_id');
            $table->unsignedBigInteger('evaluation_criterion_id');
            $table->unsignedTinyInteger('deduction_points')->comment('الدرجات المخصومة من هذا المعيار');
            $table->timestamps();

            // --- THE FIX: Define foreign keys with custom, shorter names ---
            $table->foreign('penalty_type_id', 'fk_pen_crit_pen_type')
                  ->references('id')->on('penalty_types')->onDelete('cascade');
                  
            $table->foreign('evaluation_criterion_id', 'fk_pen_crit_eval_crit')
                  ->references('id')->on('evaluation_criteria')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_type_evaluation_criterion');
    }
};