<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index()
    {
        // Eager load roles to prevent N+1 query problem
        $users = User::with('roles')->latest()->paginate(10);
        return Inertia::render('HR/Users/Index', ['users' => $users]);
    }

    /**
     * Show the form for editing the specified user's roles.
     */
    public function edit(User $user)
    {
        return Inertia::render('HR/Users/Edit', [
            'user' => $user->load('roles'), // Load current roles
            'roles' => Role::all(['id', 'name']) // Get all available roles
        ]);
    }

    /**
     * Update the specified user's roles in storage.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name' // Ensure all submitted roles exist
        ]);

        // Sync roles, this will detach any old roles and attach the new ones.
        $user->syncRoles($request->roles);

        return Redirect::route('hr.users.index')->with('success', 'تم تحديث أدوار المستخدم بنجاح.');
    }
}
