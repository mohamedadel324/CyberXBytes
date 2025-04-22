<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\EventChallange;
use App\Models\EventInvitation;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Extract challenges from the form data
        $challenges = $data['challenges'] ?? [];
        unset($data['challenges']);

        // Store challenges in session for after-save processing
        session(['pending_challenges' => $challenges]);

        return $data;
    }

    protected function afterCreate(): void
    {
        $event = $this->record;
        
        // Process challenges
        $challenges = session('pending_challenges', []);
        
        if (!empty($challenges)) {
            foreach ($challenges as $challengeData) {
                try {
                    // Add event UUID to challenge data
                    $challengeData['event_uuid'] = $event->uuid;
                    
                    // Handle flags if present
                    $flags = $challengeData['flags'] ?? [];
                    unset($challengeData['flags']);
                    
                    // Create the challenge
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
                            $flagData['ar_name'] = $flagData['ar_name'] ?? '';
                            $flagData['description'] = $flagData['description'] ?? '';
                            $flagData['order'] = $flagData['order'] ?? 0;
                            
                            $challenge->flags()->create($flagData);
                        }
                    }
                    
                    Log::info('Created challenge: ' . $challenge->title . ' for event: ' . $event->title);
                } catch (\Exception $e) {
                    Log::error('Error creating challenge: ' . $e->getMessage());
                    Log::error('Stack trace: ' . $e->getTraceAsString());
                }
            }
            
            // Clear the pending challenges
            session()->forget('pending_challenges');
        }
    }
}
