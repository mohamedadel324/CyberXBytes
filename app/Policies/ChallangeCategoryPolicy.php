<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\ChallangeCategory;
use Illuminate\Database\Eloquent\Model;

class ChallangeCategoryPolicy extends ResourcePolicy
{
    // Override the getModelName method to match the permission name in the seeder
    protected function getModelNameFromPolicy(): string
    {
        return 'challange_category';
    }
    
    // Override the getModelName method for the specific model
    protected function getModelName(Model $model): string
    {
        // Check if the model is a ChallangeCategory
        if ($model instanceof ChallangeCategory) {
            return 'challange_category';
        }
        
        // Fall back to parent implementation
        return parent::getModelName($model);
    }
} 