<?php

namespace App\Filament\Traits;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

trait HasResourcePermissions
{
    protected static function getResourcePermissionName(): string
    {
        // Get the model class name, convert to lowercase
        $modelClass = static::getModel();
        $parts = explode('\\', $modelClass);
        $modelName = strtolower(end($parts));
        
        // Special case mappings for models where the permission name doesn't match the model name
        $specialCases = [
            'challangecategory' => 'challange_category',
            'labcategory' => 'lab_category',
            'eventchallange' => 'event_challange',
            'eventchallangesubmission' => 'event_challange_submission',
            'playerTitle' => 'player_title',
            'userchallanges' => 'user_challange',
            'termsprivacy' => 'terms_privacy',
        ];
        
        // Get the resource name from the class name
        $resourceName = self::getResourceNameFromClass();
        
        // If we have a resource name, use it instead
        if ($resourceName && $resourceName !== $modelName) {
            return "manage_{$resourceName}";
        }
        
        // If this is a special case, use the mapped name
        if (isset($specialCases[$modelName])) {
            return "manage_" . $specialCases[$modelName];
        }
        
        return "manage_{$modelName}";
    }
    
    /**
     * Try to determine the resource name from the class name
     */
    protected static function getResourceNameFromClass(): ?string
    {
        $className = get_called_class();
        
        if (preg_match('/(\w+)Resource$/', $className, $matches)) {
            $resourceName = $matches[1];
            
            // Special mappings for resource names
            $specialMappings = [
                'EventChallangeSubmission' => 'event_challange_submission',
                'EventChallange' => 'event_challange',
                'ChallangeCategory' => 'challange_category',
                'LabCategory' => 'lab_category',
                'PlayerTitle' => 'player_title',
                'UserChallange' => 'user_challange',
                'TermsPrivacy' => 'terms_privacy',
            ];
            
            if (isset($specialMappings[$resourceName])) {
                return $specialMappings[$resourceName];
            }
            
            // Convert CamelCase to snake_case
            return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $resourceName));
        }
        
        return null;
    }
    
    protected static function isAdminUser(): bool
    {
        return auth()->guard('admin')->check();
    }
    
    protected static function isSuperAdmin(): bool
    {
        if (!static::isAdminUser()) {
            return false;
        }
        
        $user = auth()->guard('admin')->user();
        
        // First check for user ID being 1 (first admin)
        if ($user && $user->id === 1) {
            return true;
        }
        
        // Then check for Super Admin role using a safer approach
        try {
            // Check if user has roles
            if (!$user || (!property_exists($user, 'roles') && !method_exists($user, 'roles'))) {
                return false;
            }
            
            // Use call_user_func to avoid linter errors
            if (method_exists($user, 'hasRole')) {
                /** @var bool $result */
                $result = call_user_func([$user, 'hasRole'], 'Super Admin');
                return $result;
            }
            
            // Fallback to checking roles collection
            $roles = $user->roles;
            foreach ($roles as $role) {
                if ($role->name === 'Super Admin') {
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    protected static function hasResourcePermission(): bool
    {
        if (!static::isAdminUser()) {
            return false;
        }
        
        if (static::isSuperAdmin()) {
            return true;
        }
        
        $permissionName = static::getResourcePermissionName();
        $user = auth()->guard('admin')->user();
        
        if (!$user) {
            return false;
        }
        
        try {
            // Try using hasPermissionTo if it exists
            if (method_exists($user, 'hasPermissionTo')) {
                /** @var bool $result */
                $result = call_user_func([$user, 'hasPermissionTo'], $permissionName);
                return $result;
            }
            
            // Fallback to checking permissions collection
            if (property_exists($user, 'permissions') || method_exists($user, 'permissions')) {
                $permissions = $user->permissions;
                foreach ($permissions as $permission) {
                    if ($permission->name === $permissionName) {
                        return true;
                    }
                }
            }
            
            // Check role permissions
            if (property_exists($user, 'roles') || method_exists($user, 'roles')) {
                $roles = $user->roles;
                foreach ($roles as $role) {
                    if (property_exists($role, 'permissions') || method_exists($role, 'permissions')) {
                        $permissions = $role->permissions;
                        foreach ($permissions as $permission) {
                            if ($permission->name === $permissionName) {
                                return true;
                            }
                        }
                    }
                }
            }
            
            return false;
        } catch (\Exception $e) {
            // If an error occurs, log it but don't break the application
            error_log("Error checking permissions: " . $e->getMessage());
            return false;
        }
    }
    
    public static function canViewAny(): bool
    {
        return static::hasResourcePermission();
    }
    
    public static function canView(Model $record): bool
    {
        return static::hasResourcePermission();
    }
    
    public static function canCreate(): bool
    {
        return static::hasResourcePermission();
    }
    
    public static function canEdit(Model $record): bool
    {
        return static::hasResourcePermission();
    }
    
    public static function canDelete(Model $record): bool
    {
        return static::hasResourcePermission();
    }
    
    public static function canDeleteAny(): bool
    {
        return static::hasResourcePermission();
    }
} 