<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('uploaded_by_user_id')->constrained('users')->onDelete('cascade');
            $table->string('file_name')->comment('اسم الملف الأصلي');
            $table->string('file_path')->comment('مسار تخزين الملف');
            $table->string('file_type')->nullable()->comment('نوع الملف (MIME type)');
            $table->unsignedInteger('file_size')->nullable()->comment('حجم الملف بالبايت');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_attachments');
    }
};