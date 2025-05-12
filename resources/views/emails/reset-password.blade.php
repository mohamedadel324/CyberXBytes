<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Reset Your Password</title>
</head>
<body bgcolor="#000000" style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif;">
    <table width="100%" bgcolor="#000000" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px;">
                    <!-- Logo -->
                    <tr>
                        <td align="center" style="padding: 20px 0;">
                            <img src="{{ asset('logo3.png') }}" alt="Logo" width="60" height="60" style="border-radius: 50%;">
                        </td>
                    </tr>
                    
                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding: 10px 0;">
                            <h2 style="color: #ffffff; font-size: 22px; margin: 0;">Hi {{ $user->user_name ?? 'User' }}</h2>
                            <p style="color: #ffffff; margin: 5px 0 0 0;">Reset your {{ config('app.name') }} password</p>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td align="center">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#131619" style="border-radius: 15px; margin: 20px 0;">
                                <tr>
                                    <td align="center" style="padding: 30px;">
                                        <!-- Profile Image -->
                                        <table cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" style="padding-bottom: 30px;">
                                                    <img src="{{ $user->profile_image ?? asset('user.webp') }}" alt="Profile" width="80" height="80" style="border-radius: 50%; object-fit: cover;">
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Message -->
                                        <table cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" style="color: #ffffff; font-size: 14px; line-height: 1.5; padding-bottom: 25px;">
                                                    <p>You are receiving this email because we received a password reset request for your account.</p>
                                                    <p>This password reset link will expire in 60 minutes.</p>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Button -->
                                        <table cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" style="padding: 20px 0;">
                                                    <table cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td bgcolor="#00edb1" style="padding: 12px 30px; border-radius: 25px;">
                                                                <a href="{{ $resetUrl }}" style="color: #000000; font-weight: bold; text-decoration: none; display: inline-block; font-size: 14px;">Reset Password</a>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Additional Info -->
                                        <table cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" style="color: #ffffff; font-size: 14px; line-height: 1.5; padding-top: 10px;">
                                                    <p>If you did not request a password reset, no further action is required.</p>
                                                    <p>If you're having trouble clicking the button, copy and paste this URL into your web browser:</p>
                                                    <p><a href="{{ $resetUrl }}" style="color: #00edb1;">{{ $resetUrl }}</a></p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Social Icons -->
                    <tr>
                        <td align="center" style="padding: 20px 0;">
                            <table cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding: 0 10px;"><a href="https://discord.com" style="text-decoration: none;"><img src="https://cdn-icons-png.flaticon.com/512/5968/5968756.png" alt="Discord" width="30" height="30"></a></td>
                                    <td style="padding: 0 10px;"><a href="https://x.com" style="text-decoration: none;"><img src="https://cdn-icons-png.flaticon.com/512/733/733579.png" alt="X" width="30" height="30"></a></td>
                                    <td style="padding: 0 10px;"><a href="https://linkedin.com" style="text-decoration: none;"><img src="https://cdn-icons-png.flaticon.com/512/174/174857.png" alt="LinkedIn" width="30" height="30"></a></td>
                                    <td style="padding: 0 10px;"><a href="https://telegram.org" style="text-decoration: none;"><img src="https://cdn-icons-png.flaticon.com/512/2111/2111646.png" alt="Telegram" width="30" height="30"></a></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="color: #777777; font-size: 12px; padding: 20px 0;">
                            <p style="margin: 5px 0; text-align: center;">Copyright © 2025</p>
                            <p style="margin: 5px 0; text-align: center;">CyberXbytes</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
