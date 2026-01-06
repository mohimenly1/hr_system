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
        Schema::table('default_shift_settings', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('id')
                ->constrained('departments')->onDelete('cascade')
                ->comment('null = للمؤسسة كاملة، أو ID القسم المحدد');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('default_shift_settings', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
