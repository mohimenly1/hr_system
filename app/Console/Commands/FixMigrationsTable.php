<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixMigrationsTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrations:fix';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix migrations table AUTO_INCREMENT issue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('إصلاح جدول migrations...');

        try {
            // التحقق من وجود PRIMARY KEY
            $hasPrimaryKey = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = 'migrations'
                AND CONSTRAINT_TYPE = 'PRIMARY KEY'
            ");

            if (isset($hasPrimaryKey[0]) && $hasPrimaryKey[0]->count == 0) {
                $this->info('إضافة PRIMARY KEY...');
                DB::statement('ALTER TABLE `migrations` ADD PRIMARY KEY (`id`)');
                $this->info('✓ تم إضافة PRIMARY KEY');
            } else {
                $this->info('✓ PRIMARY KEY موجود بالفعل');
            }

            // إضافة AUTO_INCREMENT
            $this->info('إضافة AUTO_INCREMENT...');
            DB::statement('ALTER TABLE `migrations` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            $this->info('✓ تم إضافة AUTO_INCREMENT');

            // تعيين AUTO_INCREMENT إلى القيمة التالية بعد آخر ID
            $maxId = DB::table('migrations')->max('id') ?? 0;
            $nextId = $maxId + 1;
            DB::statement("ALTER TABLE `migrations` AUTO_INCREMENT = {$nextId}");
            $this->info("✓ تم تعيين AUTO_INCREMENT إلى {$nextId}");

            // إضافة السجل المفقود إذا لم يكن موجوداً
            $migrationName = '2025_01_15_000001_enhance_timetable_entries_table';
            $exists = DB::table('migrations')->where('migration', $migrationName)->exists();

            if (!$exists) {
                $maxBatch = DB::table('migrations')->max('batch') ?? 0;
                $nextBatch = $maxBatch + 1;

                $this->info("إضافة السجل المفقود: {$migrationName}...");
                DB::table('migrations')->insert([
                    'migration' => $migrationName,
                    'batch' => $nextBatch,
                ]);
                $this->info("✓ تم إضافة السجل بنجاح (batch: {$nextBatch})");
            } else {
                $this->info('✓ السجل موجود بالفعل');
            }

            $this->info('');
            $this->info('✓ تم إصلاح جدول migrations بنجاح!');
            $this->info('يمكنك الآن تشغيل: php artisan migrate');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('حدث خطأ: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
