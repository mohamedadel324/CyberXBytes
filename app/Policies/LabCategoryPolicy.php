<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\LabCategory;
use Illuminate\Database\Eloquent\Model;

class LabCategoryPolicy extends ResourcePolicy
{
    // Override the getModelName method to match the permission name in the seeder
    protected function getModelNameFromPolicy(): string
    {
        return 'lab_category';
    }
    
    // Override the getModelName method for the specific model
    protected function getModelName(Model $model): string
    {
        // Check if the model is a LabCategory
        if ($model instanceof LabCategory) {
            return 'lab_category';
        }
        
        // Fall back to parent implementation
        return parent::getModelName($model);
    }
} 