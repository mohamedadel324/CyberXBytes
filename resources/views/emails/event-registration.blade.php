@component('mail::message')
# You've Been Registered for {{ $event->title }}

Hello {{ $user->name }},

You have been automatically registered for the event: **{{ $event->title }}**.

**Event Details:**
- **Start Date:** {{ $event->start_date->format('F j, Y, g:i a') }}
- **End Date:** {{ $event->end_date->format('F j, Y, g:i a') }}

{{ $event->description }}

@component('mail::button', ['url' => $eventUrl])
View Event
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent 