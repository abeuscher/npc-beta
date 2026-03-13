<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Roles ────────────────────────────────────────────────────────────
        $roles = [
            'super_admin',
            'crm_manager',
            'staff',
            'finance_manager',
            'events_manager',
            'read_only',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // ── Admin user ───────────────────────────────────────────────────────
        $adminEmail    = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');
        $adminName     = env('ADMIN_NAME', 'Admin');

        if ($adminEmail && $adminPassword) {
            $admin = User::firstOrCreate(
                ['email' => $adminEmail],
                [
                    'name'      => $adminName,
                    'password'  => Hash::make($adminPassword),
                    'is_active' => true,
                ]
            );

            $admin->assignRole('super_admin');
        }

        // ── Home page ────────────────────────────────────────────────────────
        Page::firstOrCreate(
            ['slug' => 'home'],
            [
                'title'        => 'Welcome',
                'content'      => '<p>Welcome to the site. Edit this page in the admin panel.</p>',
                'is_published' => true,
                'published_at' => now(),
            ]
        );
    }
}
