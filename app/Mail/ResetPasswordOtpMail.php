<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $expiresIn;
    public $user;

    public function __construct($user, $otp)
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->expiresIn = '2 minutes';
    }

    public function build()
    {
        return $this->subject('Reset Your Password - Verification Code')
            ->markdown('emails.reset-password-otp', [
                'otp' => $this->otp,
                'expiresIn' => $this->expiresIn,
                'user' => $this->user
            ]);
    }
}
