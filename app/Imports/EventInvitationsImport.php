<?php

namespace App\Imports;

use App\Models\EventInvitation;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\Event;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Illuminate\Support\Facades\Log;
use App\Mail\EventRegistrationMail;
use Illuminate\Support\Facades\Mail;

class EventInvitationsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError
{
    use SkipsErrors;
    
    protected $eventUuid;
    protected $event;
    protected $rowCount = 0;
    protected $newInvitations = 0;
    protected $existingInvitations = 0;

    public function __construct($eventUuid)
    {
        $this->eventUuid = $eventUuid;
        $this->event = Event::where('uuid', $eventUuid)->firstOrFail();
        Log::info('EventInvitationsImport initialized with UUID: ' . $eventUuid);
    }

    public function model(array $row)
    {
        $this->rowCount++;
        
        // Try different possible column names for email
        $email = $row['email'] ?? $row['e-mail'] ?? $row['e_mail'] ?? $row['mail'] ?? null;
        
        if (!$email) {
            Log::error('No email column found in row', $row);
            throw new \Exception('No email column found in CSV');
        }

        Log::info('Processing invite for: ' . $email);
        
        try {
            // Check if invitation already exists
            $exists = EventInvitation::where([
                'event_uuid' => $this->eventUuid,
                'email' => $email,
            ])->exists();
            
            if ($exists) {
                $this->existingInvitations++;
                Log::info("Invitation already exists for {$email}, skipping but keeping the invitation");
            } else {
                $this->newInvitations++;
                // Use the direct method to add the user
                $this->event->addUserByEmail($email);
            }
            
            // Every 100 records, log progress
            if ($this->rowCount % 100 === 0) {
                Log::info("CSV import progress: {$this->rowCount} rows processed. New: {$this->newInvitations}, Existing: {$this->existingInvitations}");
            }
            
            return null; // Don't return a model
        } catch (\Exception $e) {
            Log::error('Error adding user: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get a summary of the import
     * 
     * @return array
     */
    public function getSummary()
    {
        return [
            'total' => $this->rowCount,
            'new' => $this->newInvitations,
            'existing' => $this->existingInvitations,
        ];
    }

    public function rules(): array
    {
        return [
            '*.email' => ['required', 'email'],
            '*.e-mail' => ['nullable', 'email'],
            '*.e_mail' => ['nullable', 'email'],
            '*.mail' => ['nullable', 'email'],
        ];
    }

    public function customValidationMessages()
    {
        return [
            '*.email.required' => 'The email field is required.',
            '*.email.email' => 'Invalid email format found in CSV.',
            '*.e-mail.email' => 'Invalid email format found in CSV.',
            '*.e_mail.email' => 'Invalid email format found in CSV.',
            '*.mail.email' => 'Invalid email format found in CSV.',
        ];
    }
}
