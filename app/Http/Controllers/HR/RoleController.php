<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Redirect;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get();
        return Inertia::render('HR/Roles/Index', ['roles' => $roles]);
    }

    public function create()
    {
        // For simplicity, we'll define permissions here. 
        // In a larger app, you might pull these from a config or database table.
        $permissions = [
            'manage employees', 'manage contracts', 'manage payroll',
            'manage leaves', 'manage attendance', 'manage roles'
        ];
        return Inertia::render('HR/Roles/Create', ['permissions' => $permissions]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'nullable|array'
        ]);

        $role = Role::create(['name' => $request->name]);

        if ($request->has('permissions')) {
            // Ensure all provided permissions exist before assigning them
            foreach ($request->permissions as $permissionName) {
                Permission::firstOrCreate(['name' => $permissionName]);
            }
            $role->givePermissionTo($request->permissions);
        }

        return Redirect::route('hr.roles.index')->with('success', 'تم إنشاء الدور بنجاح.');
    }
}
