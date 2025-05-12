<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Approved</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body style="font-family: 'Tajawal', Arial, sans-serif; margin: 0; padding: 0; background-color: #000000; color: #ffffff; text-align: center;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="margin: 20px 0;">
            <img src="{{ asset('logo3.png') }}" alt="{{ config('app.name') }} Logo" style="width: 60px; height: 60px; border-radius: 50%;">
        </div>
        
        <div style="margin: 20px 0;">
            <h2 style="color: #ffffff; font-family: 'Tajawal', Arial, sans-serif; font-weight: 700;">Hi {{ $user->user_name ?? 'User' }},</h2>
        </div>
        
        <div style="background-color: #131619; padding: 30px; margin: 20px 0; border-radius: 15px;">
            <div style="margin-bottom: 30px;">
                <img src="{{ $user->profile_image ?? asset('user.webp') }}" alt="Profile Image" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;">
            </div>
            
            <div style="font-size: 14px; line-height: 1.5; color: #ffffff; font-family: 'Tajawal', Arial, sans-serif; margin-bottom: 25px;">
                <p>For security reasons, please help us by verifying your email address.</p>
            </div>
            
            <div style="background-color: #131619; padding: 20px; margin: 20px 0; border-radius: 15px;">
                <p style="color: #ffffff; font-family: 'Tajawal', Arial, sans-serif;">Your Verification Code:</p>
                <div style="display: flex; justify-content: center; gap: 10px; margin: 15px 0;">
                    @foreach(str_split($otp) as $digit)
                    <div style="font-size: 24px; font-weight: bold; color: #ffffff; font-family: 'Tajawal', Arial, sans-serif; background-color: #1e2124; padding: 10px 15px; border-radius: 8px; min-width: 20px;">{{ $digit }}</div>
                    @endforeach
                </div>
                <p style="color: #ffffff; font-family: 'Tajawal', Arial, sans-serif;">This code will expire in 5 minutes.</p>
            </div>
            
            <div style="font-size: 14px; line-height: 1.5; color: #ffffff; font-family: 'Tajawal', Arial, sans-serif;">
                <p>Please enter this code in the verification field to complete your registration.</p>
            </div>
        </div>
        
        <div style="margin: 30px 0 15px 0;">
            <a href="https://discord.com" style="margin: 0 10px; text-decoration: none;"><img src="https://cdn-icons-png.flaticon.com/512/5968/5968756.png" alt="Discord" style="width: 24px; height: 24px;"></a>
            <a href="https://twitter.com" style="margin: 0 10px; text-decoration: none;"><img src="https://cdn-icons-png.flaticon.com/512/733/733579.png" alt="Twitter" style="width: 24px; height: 24px;"></a>
            <a href="https://linkedin.com" style="margin: 0 10px; text-decoration: none;"><img src="https://cdn-icons-png.flaticon.com/512/174/174857.png" alt="LinkedIn" style="width: 24px; height: 24px;"></a>
            <a href="https://telegram.org" style="margin: 0 10px; text-decoration: none;"><img src="https://cdn-icons-png.flaticon.com/512/2111/2111646.png" alt="Telegram" style="width: 24px; height: 24px;"></a>
        </div>
        
        <div style="margin-top: 20px; font-size: 12px; color: #777777; font-family: 'Tajawal', Arial, sans-serif;">
            <p>Copyright © {{ date('Y') }}</p>
            <p>{{ config('app.name') }}</p>
            <p>{{ config('app.tagline') }}</p>
        </div>
    </div>
</body>
</html>
