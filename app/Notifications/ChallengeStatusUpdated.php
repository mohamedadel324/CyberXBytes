<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\UserChallange;
use Illuminate\Support\Facades\Log;

class ChallengeStatusUpdated extends Notification
{
    use Queueable;

    protected $challenge;

    /**
     * Create a new notification instance.
     */
    public function __construct(UserChallange $challenge)
    {
        $this->challenge = $challenge;
        Log::info('Notification constructed', [
            'challenge_id' => $challenge->id,
            'status' => $challenge->status
        ]);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        Log::info('Notification via method called', [
            'channels' => ['mail']
        ]);
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        Log::info('Building email message', [
            'challenge_id' => $this->challenge->id,
            'user_id' => $notifiable->id,
            'user_email' => $notifiable->email
        ]);

        return (new MailMessage)
            ->subject("Challenge Status Update: {$this->challenge->name}")
            ->view('emails.challenge-status-update', [
                'user' => $notifiable,
                'challenge' => $this->challenge
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
