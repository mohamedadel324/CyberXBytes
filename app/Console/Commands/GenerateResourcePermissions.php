<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

class GenerateResourcePermissions extends Command
{
    protected $signature = 'permissions:generate';
    protected $description = 'Generate permissions for all resources';

    public function handle()
    {
        $modelsDirectory = app_path('Models');
        
        if (!File::isDirectory($modelsDirectory)) {
            $this->error("Models directory not found!");
            return 1;
        }

        $files = File::files($modelsDirectory);
        $guard = 'admin';
        
        $this->info("Generating permissions for all resources...");
        
        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            
            // Skip non-model files
            if ($filename === 'User' || $filename === 'Permission' || $filename === 'Role') {
                continue;
            }
            
            $model = strtolower($filename);
            
            $permissions = [
                "view_any_{$model}",
                "view_{$model}",
                "create_{$model}",
                "update_{$model}",
                "delete_{$model}",
                "delete_any_{$model}",
            ];
            
            foreach ($permissions as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => $guard,
                ]);
                
                $this->info("Created permission: {$permission}");
            }
        }
        
        $this->info("All permissions generated successfully!");
        
        return 0;
    }
} 