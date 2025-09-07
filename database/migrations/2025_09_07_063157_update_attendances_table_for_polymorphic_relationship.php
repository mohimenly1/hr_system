<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // الخطوة 1: حذف المفتاح الخارجي أولاً ثم القيد الفريد
            // الترتيب مهم هنا لأن المفتاح الخارجي يعتمد على القيد
            $table->dropForeign(['employee_id']);
            $table->dropUnique('attendances_employee_id_attendance_date_unique');
            
            // الخطوة 2: تغيير اسم عمود employee_id وتحويله إلى العلاقة الجديدة
            // باستخدام morphs، يتم إنشاء attendable_id و attendable_type
            $table->renameColumn('employee_id', 'attendable_id');
            $table->string('attendable_type')->after('attendable_id');

            // الخطوة 3: إضافة القيد الفريد الجديد للعلاقة متعددة الأشكال
            $table->unique(['attendable_id', 'attendable_type', 'attendance_date'], 'attendable_daily_record_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // عكس الخطوات بالترتيب المعاكس
            $table->dropUnique('attendable_daily_record_unique');
            
            $table->dropColumn('attendable_type');
            $table->renameColumn('attendable_id', 'employee_id');

            // إعادة إنشاء المفتاح الخارجي والقيد الفريد القديم
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->unique(['employee_id', 'attendance_date']);
        });
    }
};

