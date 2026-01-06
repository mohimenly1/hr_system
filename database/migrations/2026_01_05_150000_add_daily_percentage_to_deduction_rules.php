<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('deduction_rules')) {
            return;
        }

        // إضافة نوع خصم جديد daily_percentage إلى enum
        try {
            // تحديث enum لإضافة daily_percentage
            DB::statement("ALTER TABLE deduction_rules MODIFY COLUMN deduction_type ENUM('fixed', 'percentage', 'daily_salary', 'hourly_salary', 'daily_percentage') NULL");
            
            Log::info('Successfully added daily_percentage to deduction_type enum');
        } catch (\Exception $e) {
            Log::error('Failed to add daily_percentage to deduction_type enum: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('deduction_rules')) {
            try {
                // تحديث القيم من daily_percentage إلى NULL قبل إزالتها من enum
                DB::statement("UPDATE deduction_rules SET deduction_type = NULL WHERE deduction_type = 'daily_percentage'");
                
                // إعادة enum إلى القيم السابقة
                DB::statement("ALTER TABLE deduction_rules MODIFY COLUMN deduction_type ENUM('fixed', 'percentage', 'daily_salary', 'hourly_salary') NULL");
                
                Log::info('Successfully removed daily_percentage from deduction_type enum');
            } catch (\Exception $e) {
                Log::error('Failed to remove daily_percentage from deduction_type enum: ' . $e->getMessage());
            }
        }
    }
};
