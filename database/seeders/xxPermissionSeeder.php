<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class xxPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        Permission::create(['name' => 'manage employees']);
        Permission::create(['name' => 'manage contracts']);
        Permission::create(['name' => 'manage payroll']);
        Permission::create(['name' => 'manage leaves']);
        Permission::create(['name' => 'manage attendance']);
        Permission::create(['name' => 'manage roles']);
        Permission::create(['name' => 'manage users']);

        // --- NEW PERMISSIONS FOR EMPLOYEE PORTAL ---
        Permission::create(['name' => 'view own profile']);
        Permission::create(['name' => 'view own payslips']);
        Permission::create(['name' => 'request leave']);


        // Create Roles and Assign Permissions
        
        // HR Manager Role
        $hrManagerRole = Role::create(['name' => 'hr-manager']);
        $hrManagerRole->givePermissionTo([
            'manage employees',
            'manage contracts',
            'manage payroll',
            'manage leaves',
            'manage attendance',
        ]);

        // Employee Role
        $employeeRole = Role::create(['name' => 'employee']);
        $employeeRole->givePermissionTo([
            'view own profile',
            'view own payslips',
            'request leave',
        ]);

        // Admin Role (gets all permissions)
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());
    }
}

