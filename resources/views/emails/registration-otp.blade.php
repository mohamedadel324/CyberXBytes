@component('mail::message')
# Welcome to {{ config('app.name') }}!

Thank you for registering. To complete your registration, please use the following verification code:

@component('mail::panel')
# Your Verification Code: {{ $otp }}
@endcomponent

This code will expire in {{ $expiresIn }}.

**Important Notes:**
- You have 3 attempts to enter the correct code
- This code is valid for 2 minutes only
- Do not share this code with anyone

If you did not create an account, no further action is required.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
