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

class RegisterEventUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event:register-users {event_uuid} {--resend-emails : Resend emails to already registered users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register users for a specific event and send emails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $eventUuid = $this->argument('event_uuid');
        $resendEmails = $this->option('resend-emails');
        
        $event = Event::where('uuid', $eventUuid)->first();
        if (!$event) {
            $this->error("Event not found with UUID: {$eventUuid}");
            return 1;
        }
        
        $this->info("Processing event: {$event->title} ({$eventUuid})");
        
        // Find all invitations for this event
        $query = EventInvitation::where('event_uuid', $eventUuid);
        
        if (!$resendEmails) {
            // Only process unregistered invitations unless we want to resend emails
            $query->whereNull('registered_at');
        }
        
        $invitations = $query->get();
        
        $this->info("Found {$invitations->count()} invitations to process");
        
        $processed = 0;
        $registered = 0;
        $emailsSent = 0;
        
        foreach ($invitations as $invitation) {
            $processed++;
            $wasRegistered = !is_null($invitation->registered_at);
            
            $user = User::where('email', $invitation->email)->first();
            if (!$user) {
                $this->warn("No user found for email: {$invitation->email}");
                continue;
            }
            
            if (!$wasRegistered) {
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
                
                $this->info("Registered user: {$user->email}");
            } else {
                $this->line("User already registered: {$user->email}");
            }
            
            // Send email (for new registrations or if resend is requested)
            if (!$wasRegistered || $resendEmails) {
                try {
                    Mail::to($user->email)->queue(new EventRegistrationMail($event, $user));
                    $emailsSent++;
                    $this->info("Sent registration email to: {$user->email}");
                } catch (\Exception $e) {
                    Log::error('Failed to send registration email: ' . $e->getMessage());
                    $this->error("Failed to send email to {$user->email}: {$e->getMessage()}");
                }
            }
        }
        
        $this->info("Summary: Processed {$processed}, Newly Registered {$registered}, Emails sent {$emailsSent}");
        
        return 0;
    }
}
