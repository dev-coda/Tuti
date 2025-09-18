<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or update the admin user
        $user = User::updateOrCreate(
            ['email' => 'david.correav2@gmail.com'],
            [
                'name' => 'David Correa',
                'email' => 'david.correav2@gmail.com',
                'password' => Hash::make('Temporal2015'),
                'status_id' => User::ACTIVE,
                'city_id' => 1,
                'document' => '12345678',
                'phone' => '1234567890',
                'email_verified_at' => now(),
            ]
        );

        // Ensure admin role exists
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        // Assign admin role if not already assigned
        if (!$user->hasRole('admin')) {
            $user->assignRole('admin');
        }

        $this->command->info('Admin user created/updated: david.correav2@gmail.com');
    }
}
