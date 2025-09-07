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
        Schema::table('employees', function (Blueprint $table) {
            // Add detailed personal information columns
            $table->string('middle_name')->nullable()->after('employee_id');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('mother_name')->nullable()->after('last_name');
            $table->enum('marital_status', ['أعزب', 'متزوج', 'مطلق', 'أرمل'])->nullable()->after('gender');
            $table->string('nationality')->nullable()->after('marital_status');
            $table->string('national_id_number')->nullable()->unique()->after('nationality');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'middle_name',
                'last_name',
                'mother_name',
                'marital_status',
                'nationality',
                'national_id_number'
            ]);
        });
    }
};

