<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $expiresIn;

    public function __construct($otp)
    {
        $this->otp = $otp;
        $this->expiresIn = '2 minutes';
    }

    public function build()
    {
        return $this->subject('Your OTP Code')
            ->markdown('emails.otp', [
                'otp' => $this->otp,
                'expiresIn' => $this->expiresIn
            ]);
    }
}
