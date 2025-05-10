<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class ResetPermissions extends Command
{
    protected $signature = 'permissions:reset';
    
    protected $description = 'Reset all permissions and re-seed them';

    public function handle()
    {
        if ($this->confirm('This will delete all existing permissions and roles. Are you sure you want to continue?')) {
            // Run in a transaction
            DB::beginTransaction();
            
            try {
                // Clear permission cache
                app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
                
                // Delete all permissions and roles
                $this->info('Deleting all permissions and roles...');
                
                // Get IDs of roles to unassign from users
                $roleIds = Role::pluck('id')->toArray();
                
                // Delete role-permission relations
                DB::table('role_has_permissions')->delete();
                DB::table('model_has_permissions')->delete();
                DB::table('model_has_roles')->delete();
                
                // Delete permissions and roles
                Permission::query()->delete();
                Role::query()->delete();
                
                $this->info('All permissions and roles deleted.');
                
                // Now re-seed the permissions
                $this->info('Re-seeding permissions...');
                $this->call('db:seed', ['--class' => 'PermissionSeeder']);
                
                DB::commit();
                
                $this->info('Permissions reset complete!');
                
                return 0;
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error('An error occurred: ' . $e->getMessage());
                return 1;
            }
        }
        
        $this->info('Operation cancelled.');
        return 0;
    }
} 