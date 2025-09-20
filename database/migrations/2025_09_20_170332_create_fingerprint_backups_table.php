<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('fingerprint_backups', function (Blueprint $table) {
            $table->id();
            $table->integer('device_uid')->unique(); // الـ UID من جهاز البصمة
            $table->string('user_id'); // رقم الموظف
            $table->string('name');
            $table->integer('role');
            $table->longText('fingerprints_template_data'); // أهم حقل لتخزين بيانات البصمات
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fingerprint_backups');
    }
};
