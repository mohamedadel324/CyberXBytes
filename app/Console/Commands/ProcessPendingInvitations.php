<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EventInvitation;
use App\Models\Event;
use App\Models\User;
use App\Models\EventRegistration;
use App\Mail\EventRegistrationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ProcessPendingInvitations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invitations:process {event_uuid?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process any pending event invitations and register users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $eventUuid = $this->argument('event_uuid');
        
        $query = EventInvitation::query()->whereNull('registered_at');
        
        if ($eventUuid) {
            $query->where('event_uuid', $eventUuid);
            $this->info("Processing invitations for event: {$eventUuid}");
        } else {
            $this->info("Processing all pending invitations");
        }
        
        $invitations = $query->get();
        
        $this->info("Found {$invitations->count()} pending invitations");
        
        $processed = 0;
        $registered = 0;
        $emailsSent = 0;
        
        foreach ($invitations as $invitation) {
            $processed++;
            
            $user = User::where('email', $invitation->email)->first();
            if (!$user) {
                $this->warn("No user found for email: {$invitation->email}");
                continue;
            }
            
            $event = Event::where('uuid', $invitation->event_uuid)->first();
            if (!$event) {
                $this->error("Event not found for uuid: {$invitation->event_uuid}");
                continue;
            }
            
            // Register the user
            EventRegistration::firstOrCreate(
                [
                    'event_uuid' => $invitation->event_uuid,
                    'user_uuid' => $user->uuid,
                ],
                [
                    'status' => 'registered'
                ]
            );
            
            // Mark invitation as registered
            $invitation->update(['registered_at' => now()]);
            $registered++;
            
            // Send email
            try {
                Mail::to($user->email)->queue(new EventRegistrationMail($event, $user));
                $emailsSent++;
            } catch (\Exception $e) {
                Log::error('Failed to send registration email: ' . $e->getMessage());
                $this->error("Failed to send email to {$user->email}: {$e->getMessage()}");
            }
        }
        
        $this->info("Processed: {$processed}, Registered: {$registered}, Emails sent: {$emailsSent}");
        
        return 0;
    }
} 