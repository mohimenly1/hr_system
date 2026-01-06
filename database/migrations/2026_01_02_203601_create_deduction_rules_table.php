<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // التحقق من وجود الجدول أولاً
        if (!Schema::hasTable('deduction_rules')) {
        Schema::create('deduction_rules', function (Blueprint $table) {
            $table->id();
                $table->string('name')->comment('اسم معادلة الخصم');
                $table->text('description')->nullable()->comment('وصف المعادلة');
                $table->unsignedBigInteger('penalty_type_id')->comment('نوع العقوبة المرتبطة');
                $table->enum('deduction_type', ['fixed', 'percentage'])->nullable()->comment('نوع الخصم: ثابت أو نسبة مئوية (إذا كان null، سيتم استخدام القيمة من نوع العقوبة)');
                $table->decimal('deduction_amount', 10, 2)->nullable()->comment('قيمة الخصم (إذا كان null، سيتم استخدام القيمة من نوع العقوبة)');
                $table->decimal('min_deduction', 10, 2)->nullable()->comment('الحد الأدنى للخصم');
                $table->decimal('max_deduction', 10, 2)->nullable()->comment('الحد الأقصى للخصم');
                $table->json('conditions')->nullable()->comment('الشروط والقواعد (مثل: تأخير 10 دقائق لأيام غير متتالية)');
                $table->integer('priority')->default(0)->comment('الأولوية (كلما زاد الرقم زادت الأولوية)');
                $table->boolean('is_active')->default(true)->comment('هل المعادلة نشطة');
            $table->timestamps();

                // Foreign Key Constraint
                $table->foreign('penalty_type_id', 'fk_deduction_rules_penalty_type')
                      ->references('id')
                      ->on('penalty_types')
                      ->onDelete('cascade');

                // Indexes
                $table->index('penalty_type_id');
                $table->index('is_active');
                $table->index('priority');
            });
        } else {
            // إذا كان الجدول موجوداً، نضيف Foreign Key constraint فقط إذا لم يكن موجوداً
            try {
                Schema::table('deduction_rules', function (Blueprint $table) {
                    // التحقق من وجود Foreign Key constraint
                    $foreignKeys = DB::select("
                        SELECT CONSTRAINT_NAME
                        FROM information_schema.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'deduction_rules'
                        AND COLUMN_NAME = 'penalty_type_id'
                        AND REFERENCED_TABLE_NAME IS NOT NULL
                    ");

                    if (empty($foreignKeys)) {
                        $table->foreign('penalty_type_id', 'fk_deduction_rules_penalty_type')
                              ->references('id')
                              ->on('penalty_types')
                              ->onDelete('cascade');
                    }
                });
            } catch (\Exception $e) {
                // تجاهل الخطأ إذا كان Foreign Key موجوداً بالفعل
                // أو إذا كان هناك مشكلة أخرى
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deduction_rules');
    }
};
