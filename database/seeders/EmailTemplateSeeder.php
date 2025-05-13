<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $emailTypes = [
            'registration-otp' => [
                'path' => resource_path('views/emails/registration-otp.blade.php'),
                'mail_class' => app_path('Mail/RegistrationOtpMail.php'),
                'subject' => 'Complete Your Registration - Verification Code',
                'default_header' => 'Welcome to CyberXbytes! Please verify your email address.'
            ],
            'reset-password-otp' => [
                'path' => resource_path('views/emails/reset-password-otp.blade.php'),
                'mail_class' => app_path('Mail/ResetPasswordOtpMail.php'),
                'subject' => 'Reset Your Password - Verification Code',
                'default_header' => 'Password Reset Request'
            ],
            'verify-email' => [
                'path' => resource_path('views/emails/verify-email.blade.php'),
                'mail_class' => app_path('Mail/VerifyEmail.php'),
                'subject' => 'Verify Your Email Address',
                'default_header' => 'One more step to complete your registration'
            ],
            'reset-password' => [
                'path' => resource_path('views/emails/reset-password.blade.php'),
                'mail_class' => app_path('Mail/ResetPassword.php'),
                'subject' => 'Reset Your Password',
                'default_header' => 'You have requested to reset your password'
            ],
            'event-invitation' => [
                'path' => resource_path('views/emails/event-invitation.blade.php'),
                'mail_class' => app_path('Mail/EventInvitationMail.php'),
                'subject' => 'You\'ve Been Invited to an Event',
                'default_header' => 'You\'ve received an invitation!'
            ],
            'event-registration' => [
                'path' => resource_path('views/emails/event-registration.blade.php'),
                'mail_class' => app_path('Mail/EventRegistrationMail.php'),
                'subject' => 'Registration Confirmed for Event',
                'default_header' => 'Your event registration has been confirmed'
            ],
        ];

        foreach ($emailTypes as $type => $config) {
            $header = $this->extractHeaderText($config['path'], $config['default_header']);
            $subject = $this->extractSubject($config['mail_class'], $config['subject']);
            
            EmailTemplate::create([
                'type' => $type,
                'subject' => $subject,
                'header_text' => $header,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Extract header text from the email template file
     */
    private function extractHeaderText($filePath, $default)
    {
        if (File::exists($filePath)) {
            $content = File::get($filePath);
            
            // Look for the header text in the email template
            if (preg_match('/<h2.*?>(.*?)<\/h2>/s', $content, $matches)) {
                // Strip tags and blade syntax
                $header = $matches[1];
                $header = preg_replace('/{{\s*\$.*?}}/', '', $header);
                $header = trim(strip_tags($header));
                
                if (!empty($header)) {
                    return $header;
                }
            }
        }
        
        return $default;
    }
    
    /**
     * Extract subject from mail class file
     */
    private function extractSubject($filePath, $default)
    {
        if (File::exists($filePath)) {
            $content = File::get($filePath);
            
            // Look for the subject in the mail class
            if (preg_match('/->subject\([\'"](.+?)[\'"]\)/s', $content, $matches)) {
                return $matches[1];
            }
        }
        
        return $default;
    }
} 