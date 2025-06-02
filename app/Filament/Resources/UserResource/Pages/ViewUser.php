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
        
        // We need to ensure this is set as a collection that Filament can use
        $record->categoryCompletion = $categoryCompletion->values();
        
        // Get unsolved challenges
        $record->unsolvedChallenges = $this->getUnsolvedChallenges($record);
        
        return $record;
    }
    
    private function calculateCategoryCompletion(Model $user): Collection
    {
        // Debug information
        \Illuminate\Support\Facades\Log::info('Calculating category completion for user: ' . $user->id . ' - ' . $user->user_name);
        
        $categories = ChallangeCategory::all();
        
        // Get solved challenges from regular challenges table
        $solvedRegularQuery = \App\Models\Submission::where('user_uuid', $user->uuid)
            ->where('solved', true);
        
        $regularSolvedCount = $solvedRegularQuery->count();
        \Illuminate\Support\Facades\Log::info('Regular solved challenges count: ' . $regularSolvedCount);
        
        $regularSolvedChallengeIds = $solvedRegularQuery->pluck('challange_uuid')->unique()->toArray();
        \Illuminate\Support\Facades\Log::info('Regular solved challenge IDs: ' . implode(', ', $regularSolvedChallengeIds ?: ['none']));
        
        // Get solved challenges from event challenges table
        $solvedEventQuery = \App\Models\EventChallangeSubmission::where('user_uuid', $user->uuid)
            ->where('solved', true);
        
        $eventSolvedCount = $solvedEventQuery->count();
        \Illuminate\Support\Facades\Log::info('Event solved challenges count: ' . $eventSolvedCount);
        
        $eventSolvedChallengeIds = $solvedEventQuery->pluck('event_challange_id')->unique()->toArray();
        \Illuminate\Support\Facades\Log::info('Event solved challenge IDs: ' . implode(', ', $eventSolvedChallengeIds ?: ['none']));
        
        // Get solved flags
        $solvedFlagsQuery = \App\Models\EventChallangeFlagSubmission::where('user_uuid', $user->uuid)
            ->where('solved', true);
        
        $flagSolvedCount = $solvedFlagsQuery->count();
        \Illuminate\Support\Facades\Log::info('Flag solved count: ' . $flagSolvedCount);
        
        $solvedFlagIds = $solvedFlagsQuery->pluck('event_challange_flag_id')->unique()->toArray();
        \Illuminate\Support\Facades\Log::info('Solved flag IDs: ' . implode(', ', $solvedFlagIds ?: ['none']));
        
        // Combine both types of solved challenges
        $solvedChallengeIds = array_merge($regularSolvedChallengeIds, $eventSolvedChallengeIds);
        \Illuminate\Support\Facades\Log::info('Total solved challenge IDs: ' . count($solvedChallengeIds));
        
        $result = $categories->map(function ($category) use ($solvedChallengeIds, $solvedFlagIds, $user) {
            // Debug for this category
            \Illuminate\Support\Facades\Log::info('Processing category: ' . $category->name . ' (UUID: ' . $category->uuid . ')');
            
            // Get all challenges for this category
            $challengesInCategory = \App\Models\Challange::where('category_uuid', $category->uuid)->get();
            $eventChallengesInCategory = \App\Models\EventChallange::where('category_uuid', $category->uuid)->get();
            
            \Illuminate\Support\Facades\Log::info('Regular challenges in category ' . $category->name . ': ' . $challengesInCategory->count());
            \Illuminate\Support\Facades\Log::info('Event challenges in category ' . $category->name . ': ' . $eventChallengesInCategory->count());
            
            // Track which challenges are solved
            $solvedInCategory = 0;
            $totalInCategory = $challengesInCategory->count() + $eventChallengesInCategory->count();
            
            // Check regular challenges - use UUID comparison
            foreach ($challengesInCategory as $challenge) {
                \Illuminate\Support\Facades\Log::info('Checking regular challenge: ' . $challenge->id . ' - ' . $challenge->title);
                
                // Use challenge UUID for regular challenges
                if (in_array($challenge->uuid, $solvedChallengeIds)) {
                    \Illuminate\Support\Facades\Log::info('MATCH - Regular challenge solved: ' . $challenge->title);
                    $solvedInCategory++;
                }
            }
            
            // Check event challenges - use ID comparison
            foreach ($eventChallengesInCategory as $challenge) {
                \Illuminate\Support\Facades\Log::info('Checking event challenge: ' . $challenge->id . ' - ' . $challenge->title);
                
                // For single and multiple_all flag types
                if (in_array($challenge->id, $solvedChallengeIds)) {
                    \Illuminate\Support\Facades\Log::info('MATCH - Event challenge solved: ' . $challenge->title);
                    $solvedInCategory++;
                }
                // For multiple_individual flag type, check if any flag is solved
                elseif ($challenge->flag_type === 'multiple_individual') {
                    // Get flags for this challenge
                    $flagsQuery = $challenge->flags();
                    $flagIds = $flagsQuery->pluck('id')->toArray();
                    
                    \Illuminate\Support\Facades\Log::info('Challenge has multiple_individual flags: ' . count($flagIds));
                    \Illuminate\Support\Facades\Log::info('Flag IDs: ' . implode(', ', $flagIds ?: ['none']));
                    \Illuminate\Support\Facades\Log::info('Solved flag IDs: ' . implode(', ', $solvedFlagIds ?: ['none']));
                    
                    // Check if any of those flags are solved
                    $intersect = array_intersect($flagIds, $solvedFlagIds);
                    $hasAnySolved = !empty($intersect);
                    
                    \Illuminate\Support\Facades\Log::info('Intersection: ' . implode(', ', $intersect ?: ['none']));
                    \Illuminate\Support\Facades\Log::info('Has any solved: ' . ($hasAnySolved ? 'YES' : 'NO'));
                    
                    if ($hasAnySolved) {
                        \Illuminate\Support\Facades\Log::info('MATCH - Multiple individual flag challenge solved: ' . $challenge->title);
                        $solvedInCategory++;
                    }
                }
            }
            
            // Calculate percentage
            $percentage = $totalInCategory > 0 ? round(($solvedInCategory / $totalInCategory) * 100) : 0;
            
            \Illuminate\Support\Facades\Log::info('Category ' . $category->name . ' results: Solved=' . $solvedInCategory . ', Total=' . $totalInCategory . ', Percentage=' . $percentage . '%');
            
            return [
                'name' => $category->name,
                'solved_count' => (int)$solvedInCategory,
                'total_count' => (int)$totalInCategory,
                'percentage' => (int)$percentage,
            ];
        });
        
        \Illuminate\Support\Facades\Log::info('Category completion calculation completed');
        return $result;
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