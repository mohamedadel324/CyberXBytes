@component('mail::message')
# Reset Your Password

You have requested to reset your password. Please use the following verification code:

@component('mail::panel')
# Your Verification Code: {{ $otp }}
@endcomponent

This code will expire in {{ $expiresIn }}.

**Important Notes:**
- You have 3 attempts to enter the correct code
- This code is valid for 2 minutes only
- Do not share this code with anyone

If you did not request a password reset, please ignore this email.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
