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
            // أولاً، نحذف المفتاح الخارجي القديم إذا كان موجوداً
            // قد يختلف اسم المفتاح الخارجي، تأكد من الاسم في قاعدة بياناتك
            $table->dropForeign(['to_user_id']);
            $table->dropColumn('to_user_id');

            // ثانياً، نضيف الحقل الجديد مع مفتاحه الخارجي
            $table->foreignId('to_department_id')->nullable()->after('from_user_id')->constrained('departments')->onDelete('set null')->comment('القسم المستهدف بالإجراء');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_workflows', function (Blueprint $table) {
            $table->dropForeign(['to_department_id']);
            $table->dropColumn('to_department_id');

            $table->foreignId('to_user_id')->nullable()->after('from_user_id')->constrained('users')->onDelete('set null');
        });
    }
};
