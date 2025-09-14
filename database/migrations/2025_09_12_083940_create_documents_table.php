<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['outgoing', 'incoming'])->comment('نوع الوثيقة: صادر أم وارد');
            $table->string('serial_number')->unique()->comment('الرقم المسلسل الفريد للصادر/الوارد');
            $table->string('subject')->comment('موضوع الوثيقة');
            $table->longText('content')->nullable()->comment('محتوى الوثيقة (لتحرير النصوص الغني)');
            
            $table->foreignId('document_type_id')->nullable()->constrained()->onDelete('set null')->comment('نوع المستند (قرار، تعميم..)');
            $table->enum('priority', ['normal', 'urgent', 'immediate'])->default('normal')->comment('الأولوية');
            $table->enum('confidentiality_level', ['normal', 'secret', 'top_secret'])->default('normal')->comment('درجة السرية');
            
            $table->string('status')->default('draft')->comment('حالة الوثيقة الحالية في مسار العمل');
            
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade')->comment('المستخدم الذي أنشأ الوثيقة');
            $table->foreignId('department_id')->constrained()->onDelete('cascade')->comment('القسم الذي أنشأ/يملك الوثيقة');
            
            $table->foreignId('external_party_id')->nullable()->constrained()->onDelete('set null')->comment('الجهة الخارجية (مرسل/مستقبل)');
            
            // حقول خاصة بالوارد
            $table->date('received_date')->nullable()->comment('تاريخ استلام الوثيقة (للوارد)');
            $table->text('summary')->nullable()->comment('ملخص عن الوثيقة (للوارد)');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};