<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->morphs('penalizable'); // للموظف أو المعلم
            $table->foreignId('penalty_type_id')->constrained('penalty_types')->onDelete('cascade');
            $table->foreignId('issued_by_user_id')->constrained('users')->onDelete('cascade');
            $table->text('reason');
            $table->date('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalties');
    }
};
