<?php

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class ResourcePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the admin can view any models.
     */
    public function viewAny(Admin $admin): bool
    {
        // Allow Super Admin to view any resource
        if ($admin->hasRole('Super Admin')) {
            return true;
        }
        
        // Get the model from the policy class name
        $modelName = $this->getModelNameFromPolicy();
        
        // Check if admin has permission to manage this resource
        return $admin->hasPermissionTo("manage_{$modelName}");
    }

    /**
     * Determine whether the admin can view the model.
     */
    public function view(Admin $admin, Model $model): bool
    {
        // Allow Super Admin to view any model
        if ($admin->hasRole('Super Admin')) {
            return true;
        }
        
        $modelName = $this->getModelName($model);
        return $admin->hasPermissionTo("manage_{$modelName}");
    }

    /**
     * Determine whether the admin can create models.
     */
    public function create(Admin $admin): bool
    {
        // Allow Super Admin to create any resource
        if ($admin->hasRole('Super Admin')) {
            return true;
        }
        
        // Get the model from the policy class name
        $modelName = $this->getModelNameFromPolicy();
        
        // Check if admin has permission to manage this resource
        return $admin->hasPermissionTo("manage_{$modelName}");
    }

    /**
     * Determine whether the admin can update the model.
     */
    public function update(Admin $admin, Model $model): bool
    {
        // Allow Super Admin to update any model
        if ($admin->hasRole('Super Admin')) {
            return true;
        }
        
        $modelName = $this->getModelName($model);
        return $admin->hasPermissionTo("manage_{$modelName}");
    }

    /**
     * Determine whether the admin can delete the model.
     */
    public function delete(Admin $admin, Model $model): bool
    {
        // Allow Super Admin to delete any model
        if ($admin->hasRole('Super Admin')) {
            return true;
        }
        
        $modelName = $this->getModelName($model);
        return $admin->hasPermissionTo("manage_{$modelName}");
    }

    /**
     * Determine whether the admin can bulk delete models.
     */
    public function deleteAny(Admin $admin): bool
    {
        // Allow Super Admin to bulk delete any resource
        if ($admin->hasRole('Super Admin')) {
            return true;
        }
        
        // Get the model from the policy class name
        $modelName = $this->getModelNameFromPolicy();
        
        // Check if admin has permission to manage this resource
        return $admin->hasPermissionTo("manage_{$modelName}");
    }

    /**
     * Get the model name from a resource class.
     */
    protected function getModelFromResource(string $resourceClass): string
    {
        $modelClass = $resourceClass::getModel();
        $parts = explode('\\', $modelClass);
        return strtolower(end($parts));
    }

    /**
     * Get the model name from a model instance.
     */
    protected function getModelName(Model $model): string
    {
        $className = get_class($model);
        $parts = explode('\\', $className);
        return strtolower(end($parts));
    }
    
    /**
     * Get the model name from the policy class.
     */
    protected function getModelNameFromPolicy(): string
    {
        $className = get_class($this);
        $parts = explode('\\', $className);
        $policyName = end($parts);
        
        // Remove 'Policy' from the class name and convert to lowercase
        return strtolower(str_replace('Policy', '', $policyName));
    }
} 