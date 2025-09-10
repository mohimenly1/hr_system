<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            // إضافة العمود الجديد
            $table->foreignId('leave_type_id')->nullable()->after('leavable_type')->constrained()->onDelete('set null');
            // جعل العمود القديم اختيارياً تمهيداً لحذفه
            $table->string('leave_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropForeign(['leave_type_id']);
            $table->dropColumn('leave_type_id');
            $table->string('leave_type')->nullable(false)->change();
        });
    }
};
