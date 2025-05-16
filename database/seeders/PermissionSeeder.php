<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions for whole resources based on the sidebar items
        $resources = [
            // Admin resources
            'admin',
            'role',
            'permission',
            
            // Main resources
            'lab',
            'lab_category',
            'challange',
            'challange_category',
            'event',
            'event_challange_submission',
            'event_challange',
            
            // Submissions section
            'submission',
            
            // User Management section
            'user',
            'player_title',
            'user_challange',
            
            // UserChallenge Settings
            'terms_privacy',
            
            // Settings section
            'backup',
            'email_template',
        ];

        foreach ($resources as $resource) {
            Permission::firstOrCreate([
                'name' => "manage_{$resource}",
                'guard_name' => 'admin',
            ]);
        }

        // Create Super Admin role with all permissions
        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'admin',
        ]);

        $superAdmin->syncPermissions(Permission::where('guard_name', 'admin')->get());
        
        // Create other roles with specific permissions
        $labManager = Role::firstOrCreate([
            'name' => 'Lab Manager',
            'guard_name' => 'admin',
        ]);
        
        $labManager->syncPermissions(
            Permission::whereIn('name', [
                'manage_lab',
                'manage_lab_category'
            ])->get()
        );
        
        $challengeManager = Role::firstOrCreate([
            'name' => 'Challenge Manager',
            'guard_name' => 'admin',
        ]);
        
        $challengeManager->syncPermissions(
            Permission::whereIn('name', [
                'manage_challange',
                'manage_challange_category'
            ])->get()
        );
        
        $userManager = Role::firstOrCreate([
            'name' => 'User Manager',
            'guard_name' => 'admin',
        ]);
        
        $userManager->syncPermissions(
            Permission::whereIn('name', [
                'manage_user',
                'manage_player_title',
                'manage_user_challange'
            ])->get()
        );
        
        $eventManager = Role::firstOrCreate([
            'name' => 'Event Manager',
            'guard_name' => 'admin',
        ]);
        
        $eventManager->syncPermissions(
            Permission::whereIn('name', [
                'manage_event',
                'manage_event_challange',
                'manage_event_challange_submission'
            ])->get()
        );

        // Create a default Super Admin user if none exists
        if (!Admin::where('email', 'admin@example.com')->exists()) {
            $admin = Admin::create([
                'name' => 'Super Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
            ]);
            
            $admin->assignRole('Super Admin');
        }
    }
} 