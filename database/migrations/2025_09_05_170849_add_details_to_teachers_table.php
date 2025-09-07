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
        Schema::table('teachers', function (Blueprint $table) {
            // Drop columns if they exist to avoid errors on re-migration during development
            $table->dropColumn(['marital_status', 'emergency_contact_name', 'emergency_contact_phone']);

            // Add new detailed columns after 'gender'
            $table->after('gender', function (Blueprint $table) {
                $table->string('middle_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('mother_name')->nullable();
                $table->string('nationality')->nullable();
                $table->string('national_id_number')->nullable()->unique();
                $table->enum('marital_status', ['أعزب', 'متزوج', 'مطلق', 'أرمل'])->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn([
                'middle_name',
                'last_name',
                'mother_name',
                'nationality',
                'national_id_number',
                'marital_status',
            ]);
        });
    }
};
