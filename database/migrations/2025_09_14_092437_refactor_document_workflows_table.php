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
        Schema::table('document_workflows', function (Blueprint $table) {
            // حذف الحقول القديمة
            $table->dropForeign(['from_user_id']);
            $table->dropColumn('from_user_id');

            // إضافة الحقول الجديدة
            $table->foreignId('from_department_id')->nullable()->after('document_id')->constrained('departments')->onDelete('set null')->comment('القسم الذي أرسل الإجراء');
            $table->foreignId('processed_by_user_id')->nullable()->after('to_department_id')->constrained('users')->onDelete('set null')->comment('المستخدم الذي نفذ الإجراء (عند اكتماله)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_workflows', function (Blueprint $table) {
            $table->dropForeign(['from_department_id']);
            $table->dropForeign(['processed_by_user_id']);
            $table->dropColumn(['from_department_id', 'processed_by_user_id']);

            $table->foreignId('from_user_id')->nullable()->after('document_id')->constrained('users')->onDelete('set null');
        });
    }
};
