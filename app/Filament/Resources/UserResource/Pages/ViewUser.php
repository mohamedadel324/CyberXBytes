<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\ChallangeCategory;
use App\Models\Challange;
use App\Models\EventChallange;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }
    
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }
    
    protected function getHeaderActions(): array
    {
        return [];
    }
    
    public function getRecord(): Model
    {
        $record = parent::getRecord();
        
        // Calculate challenge completion by category
        $categoryCompletion = $this->calculateCategoryCompletion($record);
        $record->setAttribute('categoryCompletion', $categoryCompletion);
        
        // Get unsolved challenges
        $record->setAttribute('unsolvedChallenges', $this->getUnsolvedChallenges($record));
        
        return $record;
    }
    
    private function calculateCategoryCompletion(Model $user): Collection
    {
        $categories = ChallangeCategory::all();
        $solvedChallengeIds = $user->solvedChallenges->pluck('id')->toArray();
        
        return $categories->map(function ($category) use ($solvedChallengeIds) {
            // Get all challenges for this category
            $challengesInCategory = Challange::where('category_uuid', $category->uuid)->get();
            $eventChallengesInCategory = EventChallange::where('category_uuid', $category->uuid)->get();
            
            // Get all challenge IDs for this category
            $allChallengeIds = $challengesInCategory->pluck('id')->merge($eventChallengesInCategory->pluck('id'))->toArray();
            
            // Count solved challenges in this category
            $solvedCount = count(array_intersect($solvedChallengeIds, $allChallengeIds));
            $totalCount = count($allChallengeIds);
            
            // Calculate percentage
            $percentage = $totalCount > 0 ? round(($solvedCount / $totalCount) * 100) : 0;
            
            return [
                'name' => $category->name,
                'solved_count' => $solvedCount,
                'total_count' => $totalCount,
                'percentage' => $percentage,
            ];
        });
    }
    
    private function getUnsolvedChallenges(Model $user): Collection
    {
        $solvedChallengeIds = $user->solvedChallenges->pluck('id')->toArray();
        
        // Get regular challenges that are not solved
        $unsolvedChallenges = Challange::whereNotIn('id', $solvedChallengeIds)->get();
        
        // Get event challenges that are not solved
        $unsolvedEventChallenges = EventChallange::whereNotIn('id', $solvedChallengeIds)->get();
        
        // Combine both collections
        return $unsolvedChallenges->merge($unsolvedEventChallenges);
    }
} 