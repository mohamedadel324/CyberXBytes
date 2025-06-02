<?php

namespace App\Filament\Resources\UserChallangeResource\Pages;

use App\Filament\Resources\UserChallangeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Notifications\ChallengeStatusUpdated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EditUserChallange extends EditRecord
{
    protected static string $resource = UserChallangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        Log::info('After save triggered', [
            'record_id' => $this->record->id,
            'old_status' => $this->record->getOriginal('status'),
            'new_status' => $this->record->status,
            'was_changed' => $this->record->wasChanged('status'),
            'user_id' => $this->record->user->id,
            'user_email' => $this->record->user->email
        ]);

        if ($this->record->wasChanged('status')) {
            try {
                // Force refresh the relationship
                $this->record->load('user');
                
                if (!$this->record->user) {
                    Log::error('User not found for notification', [
                        'challenge_id' => $this->record->id
                    ]);
                    return;
                }

                if (!$this->record->user->email) {
                    Log::error('User has no email address', [
                        'user_id' => $this->record->user->id,
                        'challenge_id' => $this->record->id
                    ]);
                    return;
                }

                $this->record->user->notify(new ChallengeStatusUpdated($this->record));
                
                Log::info('Notification sent successfully', [
                    'user_id' => $this->record->user->id,
                    'user_email' => $this->record->user->email,
                    'challenge_id' => $this->record->id
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send notification', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'user_id' => $this->record->user->id ?? null,
                    'challenge_id' => $this->record->id
                ]);
            }
        }
    }
}
