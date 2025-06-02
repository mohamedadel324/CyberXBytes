<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {email : The email address to send to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email to check mail configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $this->info("Attempting to send test email to: {$email}");
        Log::info("Test email command started for: {$email}");

        try {
            // Get mail config for debugging
            $this->info("Mail driver: " . config('mail.default'));
            $this->info("Mail host: " . config('mail.mailers.smtp.host'));
            $this->info("Mail port: " . config('mail.mailers.smtp.port'));
            $this->info("Mail from address: " . config('mail.from.address'));
            
            Log::info("Mail configuration: ", [
                'driver' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'from' => config('mail.from.address'),
                'encryption' => config('mail.mailers.smtp.encryption'),
            ]);

            // Send a simple plain text email
            Mail::raw('This is a test email from the Laravel application to verify email functionality.', function($message) use ($email) {
                $message->to($email)
                    ->subject('Test Email from Laravel App');
            });
            
            $this->info("Email sent successfully!");
            Log::info("Test email sent successfully to: {$email}");
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Email sending failed: " . $e->getMessage());
            Log::error("Test email failed: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return 1;
        }
    }
} 