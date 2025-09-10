<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // إعادة تعيين الكاش الخاص بالأدوار والصلاحيات
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // --- إنشاء جميع الصلاحيات اللازمة للنظام ---
        // (باستخدام findOrCreate لضمان عدم حدوث أخطاء)
        $permissions = [
            // HR & Admin Permissions
            'manage employees',
            'manage contracts',
            'manage payroll',
            'manage leaves',
            'manage attendance',
            'manage roles',
            'manage users',
            'manage departments',
            'access integrations',
            
            // Employee & Teacher Permissions
            'view own profile',
            'view own payslips',
            'request leave',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // --- إنشاء الأدوار الأساسية وإسناد الصلاحيات لها ---

        // دور الموظف
        $employeeRole = Role::findOrCreate('employee', 'web');
        $employeeRole->syncPermissions([
            'view own profile',
            'view own payslips',
            'request leave',
        ]);
        
        // دور المعلم (نفس صلاحيات الموظف حالياً)
        $teacherRole = Role::findOrCreate('teacher', 'web');
        $teacherRole->syncPermissions([
            'view own profile',
            'view own payslips',
            'request leave',
        ]);
        
        // دور مدير القسم
        $deptManagerRole = Role::findOrCreate('department-manager', 'web');
        $deptManagerRole->syncPermissions([
            'manage employees', // يمكنه إدارة الموظفين (سيتم تقييدها لاحقاً بالقسم الخاص به فقط)
            'manage attendance',
            'manage leaves',
            'view own profile', // مدير القسم هو موظف أيضاً
        ]);
        
        // دور مدير الموارد البشرية
        $hrManagerRole = Role::findOrCreate('hr-manager', 'web');
        $hrManagerRole->syncPermissions([
            'manage employees',
            'manage contracts',
            'manage payroll',
            'manage leaves',
            'manage attendance',
            'manage departments',
        ]);

        // دور المدير العام (يمتلك كل الصلاحيات)
        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->givePermissionTo(Permission::all());

    }
}
