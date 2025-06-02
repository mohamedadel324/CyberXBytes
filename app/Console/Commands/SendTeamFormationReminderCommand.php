<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Mail\TeamFormationReminderMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendTeamFormationReminderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event:send-reminders {event_uuid : The UUID of the event}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send team formation reminders to all registered users for a specific event';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $eventUuid = $this->argument('event_uuid');
        $event = Event::where('uuid', $eventUuid)->first();
        
        if (!$event) {
            $this->error("Event with UUID: {$eventUuid} not found.");
            return 1;
        }
        
        $this->info("Sending team formation reminders for event: {$event->title}");
        
        // Get all registered users for this event
        $registrations = EventRegistration::where('event_uuid', $eventUuid)
            ->with('user')
            ->get();
            
        $this->info("Found {$registrations->count()} registrations");
        
        $sentCount = 0;
        $failedCount = 0;
        $errors = [];
        
        $progressBar = $this->output->createProgressBar($registrations->count());
        $progressBar->start();
        
        foreach ($registrations as $registration) {
            if (!$registration->user) {
                $this->error("Registration ID: {$registration->id} has no associated user.");
                $failedCount++;
                $progressBar->advance();
                continue;
            }
            
            try {
                $this->info("Sending reminder to: {$registration->user->email}");
                
                // Send reminder email
                Mail::to($registration->user->email)
                    ->send(new TeamFormationReminderMail($event, $registration->user));
                
                $sentCount++;
            } catch (\Exception $e) {
                $errorMsg = "Failed to send reminder to {$registration->user->email}: {$e->getMessage()}";
                Log::error($errorMsg);
                Log::error($e->getTraceAsString());
                $errors[] = $errorMsg;
                $failedCount++;
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        if ($sentCount > 0) {
            $this->info("Successfully sent {$sentCount} reminder emails.");
        }
        
        if ($failedCount > 0) {
            $this->warn("Failed to send {$failedCount} reminders.");
            
            if ($this->option('verbose')) {
                $this->error("Error details:");
                foreach ($errors as $error) {
                    $this->line(" - {$error}");
                }
            }
        }
        
        return $sentCount > 0 ? 0 : 1;
    }
}
