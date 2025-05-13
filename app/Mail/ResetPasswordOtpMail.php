<?php

namespace App\Mail;

use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class ResetPasswordOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $expiresIn;
    public $user;
    protected $emailTemplateService;

    public function __construct($user, $otp)
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->expiresIn = '2 minutes';
        $this->emailTemplateService = App::make(EmailTemplateService::class);
    }

    public function build()
    {
        $type = 'reset-password-otp';
        $defaultSubject = 'Reset Your Password - Verification Code';
        $defaultHeaderText = 'Password Reset Request';

        $subject = $this->emailTemplateService->getSubject($type, $defaultSubject);
        $headerText = $this->emailTemplateService->getHeaderText($type, $defaultHeaderText);

        return $this->subject($subject)
            ->markdown('emails.reset-password-otp', [
                'otp' => $this->otp,
                'expiresIn' => $this->expiresIn,
                'user' => $this->user,
                'headerText' => $headerText
            ]);
    }
}
