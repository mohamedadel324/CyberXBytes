<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SyncResourcePermissions extends Command
{
    protected $signature = 'permissions:sync {--resource= : The resource to sync permissions for} {--role= : The role to sync permissions to}';
    protected $description = 'Sync permissions for resources to roles';

    public function handle()
    {
        $resourceName = $this->option('resource');
        $roleName = $this->option('role');
        
        if (!$resourceName) {
            $this->error('Resource name is required. Use --resource=resource_name');
            return 1;
        }
        
        $resourceName = strtolower($resourceName);
        
        $actions = [
            'view_any',
            'view',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
        
        $this->info("Creating permissions for resource: {$resourceName}");
        
        $createdPermissions = [];
        foreach ($actions as $action) {
            $permissionName = "{$action}_{$resourceName}";
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'admin',
            ]);
            
            $createdPermissions[] = $permission;
            $this->info("Created permission: {$permissionName}");
        }
        
        if ($roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'admin')->first();
            
            if (!$role) {
                $this->error("Role '{$roleName}' not found!");
                return 1;
            }
            
            $role->givePermissionTo($createdPermissions);
            $this->info("Permissions added to role: {$roleName}");
        }
        
        $this->info('All permissions created successfully!');
        
        return 0;
    }
} 