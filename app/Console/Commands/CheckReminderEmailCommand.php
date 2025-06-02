<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Event;
use App\Models\User;
use App\Mail\TeamFormationReminderMail;

class CheckReminderEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:check-reminder {event_uuid} {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the TeamFormationReminderMail class';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $eventUuid = $this->argument('event_uuid');
        $emailArg = $this->argument('email');
        
        try {
            // Find the event
            $event = Event::where('uuid', $eventUuid)->first();
            if (!$event) {
                $this->error("Event not found with UUID: {$eventUuid}");
                return 1;
            }
            
            $this->info("Found event: {$event->title}");
            
            // Get a user email
            $email = $emailArg;
            if (!$email) {
                $registration = $event->registrations()->with('user')->first();
                if ($registration && $registration->user) {
                    $email = $registration->user->email;
                    $this->info("Using first registered user's email: {$email}");
                } else {
                    $this->error("No registered users found for this event. Please provide an email address.");
                    return 1;
                }
            }
            
            // Create a fake user if using custom email
            $user = User::where('email', $email)->first();
            if (!$user && $emailArg) {
                $user = new User();
                $user->email = $email;
                $user->user_name = "Test User";
                $this->info("Created temporary user object for email: {$email}");
            }
            
            // Debug the mailer setup
            $this->info("Mail Configuration:");
            $this->info("Driver: " . config('mail.default'));
            $this->info("Host: " . config('mail.mailers.smtp.host'));
            $this->info("Port: " . config('mail.mailers.smtp.port'));
            $this->info("From Address: " . config('mail.from.address'));
            
            // Check if the mail template exists
            $templatePath = 'emails.team-formation-reminder';
            $this->info("Checking for view template: {$templatePath}");
            if (view()->exists($templatePath)) {
                $this->info("✓ Template exists");
            } else {
                $this->error("✗ Template does not exist");
                // List available views in the emails directory
                $this->info("Available email templates:");
                $paths = \Illuminate\Support\Facades\View::getFinder()->getPaths();
                foreach ($paths as $path) {
                    if (file_exists($path . '/emails')) {
                        $files = scandir($path . '/emails');
                        foreach ($files as $file) {
                            if ($file != '.' && $file != '..') {
                                $this->line(" - emails.{$file}");
                            }
                        }
                    }
                }
                return 1;
            }
            
            // Try rendering the template
            $this->info("Trying to render the template...");
            try {
                $eventUrl = config('app.url') . '/events/' . $event->uuid;
                $content = view($templatePath, [
                    'user' => $user,
                    'event' => $event,
                    'eventUrl' => $eventUrl,
                ])->render();
                $this->info("✓ Template renders successfully");
                $this->info("Template content (first 200 chars):");
                $this->line(substr($content, 0, 200) . "...");
            } catch (\Exception $e) {
                $this->error("✗ Template rendering failed: " . $e->getMessage());
                return 1;
            }
            
            // Create the mailable
            $this->info("Creating TeamFormationReminderMail instance...");
            try {
                $mail = new TeamFormationReminderMail($event, $user);
                $this->info("✓ Mailable created successfully");
            } catch (\Exception $e) {
                $this->error("✗ Mailable creation failed: " . $e->getMessage());
                return 1;
            }
            
            // Try to send the email
            $this->info("Attempting to send the email to {$email}...");
            try {
                Mail::to($email)->send($mail);
                $this->info("✓ Email sent successfully!");
                return 0;
            } catch (\Exception $e) {
                $this->error("✗ Email sending failed: " . $e->getMessage());
                Log::error("Reminder email test failed: " . $e->getMessage());
                Log::error($e->getTraceAsString());
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error("Command failed: " . $e->getMessage());
            Log::error("Check reminder email command failed: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return 1;
        }
    }
} 