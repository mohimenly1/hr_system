<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade')->comment('الوثيقة المرتبطة بهذه الحركة');
            $table->foreignId('from_user_id')->nullable()->constrained('users')->onDelete('set null')->comment('المستخدم الذي أرسل الإجراء');
            $table->foreignId('to_user_id')->nullable()->constrained('users')->onDelete('set null')->comment('المستخدم المستهدف بالإجراء');
            
            $table->string('action')->comment('نوع الإجراء (create, review, approve, reject, forward, execute...)');
            $table->text('notes')->nullable()->comment('ملاحظات على هذا الإجراء');
            
            $table->string('signature_image_path')->nullable()->comment('مسار صورة التوقيع الإلكتروني (إذا تم التوقيع في هذه الخطوة)');
            
            $table->date('due_date')->nullable()->comment('الموعد النهائي لتنفيذ هذا الإجراء');
            $table->timestamp('completed_at')->nullable()->comment('توقيت إتمام هذا الإجراء');
            
            $table->timestamps(); // يسجل وقت إنشاء هذه الخطوة
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_workflows');
    }
};