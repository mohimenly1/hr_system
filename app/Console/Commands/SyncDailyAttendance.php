<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\HR\FingerprintDeviceController; // تأكد من المسار الصحيح
use Carbon\Carbon;

class SyncDailyAttendance extends Command
{
    protected $signature = 'attendance:sync-daily';
    protected $description = 'Sync yesterday\'s attendance records from the fingerprint device';

    public function handle()
    {
        $this->info('Starting daily attendance sync...');
        
        // جلب سجلات يوم أمس
        $yesterday = Carbon::yesterday();
        
        // استدعاء الدالة من الكنترولر
        // ملاحظة: هذه ليست أفضل طريقة، الأفضل نقل منطق المزامنة إلى Service Class
        // ولكن لغرض التبسيط، سنقوم بإنشاء نسخة من الكنترولر.
        $controller = new FingerprintDeviceController();
        
        // بما أن الدالة private، نحتاج إلى استخدام Reflection أو جعلها public
        // الأسهل الآن هو جعل `syncForDateRange` عامة (public) بدلاً من خاصة (private).
        // قم بتغيير `private function syncForDateRange` إلى `public function syncForDateRange`
        
        $result = $controller->syncForDateRange($yesterday, $yesterday);

        $this->info("Sync Result: " . $result['message']);
        
        return Command::SUCCESS;
    }
}
