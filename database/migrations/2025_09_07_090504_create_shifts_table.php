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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('اسم الدوام، مثل: دوام صباحي');
            $table->time('start_time')->comment('وقت بدء الدوام');
            $table->time('end_time')->comment('وقت انتهاء الدوام');
            $table->unsignedInteger('grace_period_minutes')->default(0)->comment('فترة السماح للتأخير بالدقائق');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
