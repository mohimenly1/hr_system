<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Department::create(['name' => 'الإدارة']);
        Department::create(['name' => 'تقنية المعلومات']);
        Department::create(['name' => 'الموارد البشرية']);
        Department::create(['name' => 'المالية والمحاسبة']);
        Department::create(['name' => 'التسويق والمبيعات']);
    }
}
