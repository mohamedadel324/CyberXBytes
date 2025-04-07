<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public $eventInvitation;
    public $event;

    public function __construct($eventInvitation, $event)
    {
        $this->eventInvitation = $eventInvitation;
        $this->event = $event;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to {$this->event->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.event-invitation',
            with: [
                'eventName' => $this->event->name,
                'eventDate' => $this->event->start_date->format('F j, Y'),
                'invitationUrl' => url("/register/{$this->eventInvitation->invitation_token}"),
            ],
        );
    }
}
