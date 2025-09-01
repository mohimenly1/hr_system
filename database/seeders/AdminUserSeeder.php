<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Creates an admin user if one doesn't already exist with this email
        User::firstOrCreate(
            ['email' => 'admin@example.com'], // Check for existence using the email
            [
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'), // You can change 'password' to something more secure
                'email_verified_at' => now(), // Mark email as verified
            ]
        );
    }
}
