<?php

namespace App\Mail;

use App\Models\EventInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invitation;

    public function __construct(EventInvitation $invitation)
    {
        $this->invitation = $invitation;
    }

    public function build()
    {
        $registrationUrl = config('app.url') . '/events/register/' . $this->invitation->invitation_token;

        return $this->subject('You\'re Invited to ' . $this->invitation->event->title)
            ->markdown('emails.event-invitation', [
                'event' => $this->invitation->event,
                'registrationUrl' => $registrationUrl,
            ]);
    }
}
