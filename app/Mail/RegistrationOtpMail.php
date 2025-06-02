<?php

namespace App\Mail;

use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class RegistrationOtpMail extends Mailable
{
    use SerializesModels;

    public $otp;
    public $expiresIn;
    public $user;
    protected $emailTemplateService;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $otp)
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->expiresIn = '5 minutes';
        $this->emailTemplateService = App::make(EmailTemplateService::class);
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $type = 'registration-otp';
        $defaultSubject = 'Complete Your Registration - Verification Code';
        $defaultHeaderText = 'Hi ' . ($this->user->user_name ?? 'User');

        $subject = $this->emailTemplateService->getSubject($type, $defaultSubject);
        $headerText = $this->emailTemplateService->getHeaderText($type, $defaultHeaderText);

        return $this->subject($subject)
            ->priority(1) // Set high priority
            ->markdown('emails.registration-otp', [
                'otp' => $this->otp,
                'expiresIn' => $this->expiresIn,
                'user' => $this->user,
                'headerText' => $headerText
            ]);
    }
}
