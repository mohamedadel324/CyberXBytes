<!DOCTYPE html>
<html>
<head>
    <title>Verify Your Email Address</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
        <h2 style="color: #333; text-align: center;">Welcome to {{ config('app.name') }}!</h2>
        
        <p>Hi {{ $user->name }},</p>
        
        <p>Thank you for registering with us. Please click the button below to verify your email address:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $verificationUrl }}" 
               style="background: #4CAF50; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">
                Verify Email Address
            </a>
        </div>
        
        <p>If you did not create an account, no further action is required.</p>
        
        <p>Best regards,<br>{{ config('app.name') }} Team</p>
        
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        
        <p style="color: #666; font-size: 12px; text-align: center;">
            If you're having trouble clicking the button, copy and paste this URL into your web browser:<br>
            <a href="{{ $verificationUrl }}" style="color: #4CAF50;">{{ $verificationUrl }}</a>
        </p>
    </div>
</body>
</html>
