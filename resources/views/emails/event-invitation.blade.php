@component('mail::message')
# You're Invited!

You have been invited to attend **{{ $eventName }}** on {{ $eventDate }}.

Click the button below to register for the event:

@component('mail::button', ['url' => $invitationUrl])
Register Now
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
