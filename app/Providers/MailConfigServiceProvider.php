<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;

class MailConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Add custom headers to all emails to improve deliverability
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $message = $event->message;
            $headers = $message->getHeaders();
            
            $headers->addTextHeader('X-Priority', '3');
            $headers->addTextHeader('X-Mailer', 'CyberXbytes');
            $headers->addTextHeader('X-Contact', config('mail.from.address'));
            
            // Set reply-to address if not already set
            if (empty($message->getReplyTo())) {
                $message->replyTo(
                    config('mail.reply_to.address', config('mail.from.address')),
                    config('mail.reply_to.name', config('mail.from.name'))
                );
            }
        });
    }
} 