<?php

require_once __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// Reset permission cache
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

echo "Starting admin restoration...\n";

// Step 1: Make sure all permissions exist
$resources = [
    'admin', 'role', 'permission', 'lab', 'lab_category', 'challange', 'challange_category',
    'event', 'event_challange_submission', 'event_challange', 'submission', 'user',
    'player_title', 'user_challange', 'terms_privacy', 'backup', 'email_template', 'ad',
];

echo "Creating/confirming permissions...\n";
foreach ($resources as $resource) {
    Permission::firstOrCreate([
        'name' => "manage_{$resource}",
        'guard_name' => 'admin',
    ]);
}

// Step 2: Create or update Super Admin role
echo "Setting up Super Admin role...\n";
$superAdmin = Role::firstOrCreate([
    'name' => 'Super Admin',
    'guard_name' => 'admin',
]);

// Assign all admin permissions to Super Admin role
$superAdmin->syncPermissions(Permission::where('guard_name', 'admin')->get());

// Step 3: Create a new admin with Super Admin role
echo "Creating new admin account...\n";
$admin = Admin::firstOrCreate(
    ['email' => 'mohamedmersal858@gmail.com'],
    [
        'name' => 'Mohamed Mersal',
        'password' => Hash::make('Admin@123!'),
    ]
);

// Step 4: Assign Super Admin role to the admin
echo "Assigning Super Admin role...\n";
$admin->syncRoles([$superAdmin->id]);

echo "\n-------------------------------------------------\n";
echo "✅ Admin restoration completed successfully!\n\n";
echo "Login details:\n";
echo "Email: mohamedmersal858@gmail.com\n";
echo "Password: Admin@123!\n";
echo "-------------------------------------------------\n";
