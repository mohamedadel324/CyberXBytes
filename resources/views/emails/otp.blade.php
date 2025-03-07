@component('mail::message')
# Your OTP Code

Your One-Time Password (OTP) is: **{{ $otp }}**

This code will expire in {{ $expiresIn }}.

Please do not share this code with anyone.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
