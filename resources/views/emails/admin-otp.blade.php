<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your Admin Login OTP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #f9f9f9;
            border-radius: 5px;
            padding: 20px;
            border: 1px solid #ddd;
        }
        .header {
            background-color: #0e9391;
            color: white;
            padding: 10px;
            text-align: center;
            border-radius: 5px 5px 0 0;
            margin: -20px -20px 20px;
        }
        .otp-container {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
            margin: 20px 0;
        }
        .otp {
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 5px;
            color: #0e9391;
        }
        .footer {
            font-size: 12px;
            color: #777;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Login OTP</h1>
        </div>
        
        <p>{{ $name }},</p>
        
        <p>You are receiving this email because you requested to login to the admin panel. To complete your login, please use the following One-Time Password (OTP):</p>
        
        <div class="otp-container">
            <div class="otp">{{ $otp }}</div>
        </div>
        
        <p>This OTP will expire in 5 minutes.</p>
        
        <p>If you did not request this login, please ignore this email or contact the administrator immediately.</p>
        
        <p>Thank you,<br>Admin Team</p>
        
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html> 