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
        Schema::create('deduction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_evaluation_id')->constrained()->onDelete('cascade');
            $table->foreignId('penalty_id')->constrained()->onDelete('cascade');
            $table->foreignId('evaluation_criterion_id')->constrained()->onDelete('cascade');
            $table->foreignId('logged_by_user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedTinyInteger('points_deducted');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deduction_logs');
    }
};
