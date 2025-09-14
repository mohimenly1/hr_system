<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // The user who performed the action
            $table->morphs('subject'); // The model being acted upon (e.g., Penalty, Evaluation)
            $table->string('action'); // e.g., 'created_penalty', 'created_evaluation'
            $table->json('details')->nullable(); // For extra context
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
