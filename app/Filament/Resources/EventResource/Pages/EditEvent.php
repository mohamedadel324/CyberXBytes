<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\EventChallange;
use App\Models\EventInvitation;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use App\Filament\Resources\EventResource\Widgets\EventRegistrationsWidget;
use App\Filament\Resources\EventResource\Widgets\TeamsWidget;
use App\Filament\Resources\EventResource\Widgets\ChallengesSolvedWidget;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            EventRegistrationsWidget::class,
            TeamsWidget::class,
            ChallengesSolvedWidget::class,
        ];
    }
    
    public function getHeaderWidgetsColumns(): int | string | array
    {
        return 3;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract challenges from the form data
        $challenges = $data['challenges'] ?? [];
        unset($data['challenges']);

        // Store challenges in session for after-save processing
        session(['pending_challenges' => $challenges]);

        return $data;
    }

    protected function afterSave(): void
    {
        $event = $this->record;
        
        // Process challenges
        $challenges = session('pending_challenges', []);
        
        if (!empty($challenges)) {
            // Delete existing challenges if needed
            // Uncomment the line below if you want to replace all challenges
            // $event->challenges()->delete();
            
            foreach ($challenges as $challengeData) {
                try {
                    // Add event UUID to challenge data
                    $challengeData['event_uuid'] = $event->uuid;
                    
                    // Handle flags if present
                    $flags = $challengeData['flags'] ?? [];
                    unset($challengeData['flags']);
                    
                    // Check if challenge already exists
                    if (isset($challengeData['id'])) {
                        $challenge = EventChallange::find($challengeData['id']);
                        if ($challenge) {
                            // Update existing challenge
                            $challenge->update($challengeData);
                            
                            // Handle flags
                            if (!empty($flags)) {
                                // Delete existing flags
                                $challenge->flags()->delete();
                                
                                // Create new flags
                                foreach ($flags as $flagData) {
                                    // Ensure required fields are present
                                    if (!isset($flagData['flag'])) {
                                        continue;
                                    }
                                    
                                    // Set default values for optional fields
                                    $flagData['event_challange_id'] = $challenge->id;
                                    $flagData['bytes'] = $flagData['bytes'] ?? 0;
                                    $flagData['firstBloodBytes'] = $flagData['firstBloodBytes'] ?? 0;
                                    $flagData['name'] = $flagData['name'] ?? '';
                                    $flagData['description'] = $flagData['description'] ?? '';
                                    $flagData['order'] = $flagData['order'] ?? 0;
                                    
                                    $challenge->flags()->create($flagData);
                                }
                            }
                            
                            Log::info('Updated challenge: ' . $challenge->title . ' for event: ' . $event->title);
                        }
                    } else {
                        // Create new challenge
                        $challenge = EventChallange::create($challengeData);
                        
                        // Create flags if any
                        if (!empty($flags)) {
                            foreach ($flags as $flagData) {
                                // Ensure required fields are present
                                if (!isset($flagData['flag'])) {
                                    continue;
                                }
                                
                                // Set default values for optional fields
                                $flagData['event_challange_id'] = $challenge->id;
                                $flagData['bytes'] = $flagData['bytes'] ?? 0;
                                $flagData['firstBloodBytes'] = $flagData['firstBloodBytes'] ?? 0;
                                $flagData['name'] = $flagData['name'] ?? '';
                                $flagData['description'] = $flagData['description'] ?? '';
                                $flagData['order'] = $flagData['order'] ?? 0;
                                
                                $challenge->flags()->create($flagData);
                            }
                        }
                        
                        Log::info('Created challenge: ' . $challenge->title . ' for event: ' . $event->title);
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing challenge: ' . $e->getMessage());
                    Log::error('Stack trace: ' . $e->getTraceAsString());
                }
            }
            
            // Clear the pending challenges
            session()->forget('pending_challenges');
        }
    }
}
