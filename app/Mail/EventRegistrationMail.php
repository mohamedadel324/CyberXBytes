<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $event;
    public $user;

    /**
     * Create a new message instance.
     *
     * @param Event $event
     * @param User $user
     * @return void
     */
    public function __construct(Event $event, User $user)
    {
        $this->event = $event;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('You\'ve Been Registered for ' . $this->event->title)
            ->markdown('emails.event-registration', [
                'event' => $this->event,
                'user' => $this->user,
                'eventUrl' => "https://cyberxbytes.com/events/" . $this->event->uuid,
            ]);
    }
} 