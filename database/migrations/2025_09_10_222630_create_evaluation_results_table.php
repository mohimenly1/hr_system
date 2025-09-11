<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_evaluation_id')->constrained()->onDelete('cascade');
            $table->foreignId('evaluation_criterion_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('manager_score')->nullable();
            $table->unsignedTinyInteger('admin_score')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_results');
    }
};
