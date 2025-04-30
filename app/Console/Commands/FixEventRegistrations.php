<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\User;
use App\Models\EventRegistration;
use App\Mail\EventRegistrationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class FixEventRegistrations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event:fix-registrations {event_uuid?} {--force-email : Force send emails to all users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix all event registrations and emails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $eventUuid = $this->argument('event_uuid');
        $forceEmail = $this->option('force-email');
        
        $eventsQuery = Event::query();
        
        if ($eventUuid) {
            $eventsQuery->where('uuid', $eventUuid);
            $this->info("Processing specific event: {$eventUuid}");
        } else {
            $this->info("Processing all events");
        }
        
        $events = $eventsQuery->get();
        
        if ($events->isEmpty()) {
            $this->error("No events found to process");
            return 1;
        }
        
        $this->info("Found {$events->count()} events to process");
        
        foreach ($events as $event) {
            $this->info("Processing event: {$event->title} ({$event->uuid})");
            
            // Get all invitations for this event
            $invitations = EventInvitation::where('event_uuid', $event->uuid)->get();
            
            $this->info("  Found {$invitations->count()} invitations");
            
            foreach ($invitations as $invitation) {
                // Check if user exists
                $user = User::where('email', $invitation->email)->first();
                
                if (!$user) {
                    $this->warn("  No user found for email: {$invitation->email}");
                    continue;
                }
                
                // Create or update registration
                $registration = EventRegistration::firstOrCreate(
                    [
                        'event_uuid' => $event->uuid,
                        'user_uuid' => $user->uuid,
                    ],
                    [
                        'status' => 'registered'
                    ]
                );
                
                if (!$invitation->registered_at) {
                    $invitation->registered_at = now();
                    $invitation->save();
                    $this->info("  Marked invitation as registered for {$invitation->email}");
                }
                
                // Send email if not already registered or force option is used
                if (!$invitation->registered_at || $forceEmail) {
                    try {
                        Mail::to($user->email)->send(new EventRegistrationMail($event, $user));
                        $this->info("  Sent registration email to: {$user->email}");
                    } catch (\Exception $e) {
                        $this->error("  Failed to send email to {$user->email}: {$e->getMessage()}");
                    }
                }
            }
        }
        
        $this->info("All events processed successfully");
        
        return 0;
    }
} 