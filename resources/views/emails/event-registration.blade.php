<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration</title>
    <style type="text/css">
        @media only screen and (max-width: 600px) {
            .main-table {
                width: 100% !important;
            }
            .mobile-padding {
                padding-left: 15px !important;
                padding-right: 15px !important;
            }
            .content-padding {
                padding: 20px !important;
            }
            .header-padding {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }
            .social-icon {
                width: 25px !important;
                height: 25px !important;
            }
            .button-container {
                width: 100% !important;
            }
            .event-link {
                word-break: break-all !important;
            }
            .event-details {
                padding-left: 0 !important;
            }
        }
    </style>
</head>
<body bgcolor="#000000" style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <table role="presentation" width="100%" bgcolor="#000000" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" class="main-table" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; width: 100%;">
                    <!-- Logo -->
                    <tr>
                        <td align="center" style="padding: 20px 0;">
                            <img src="{{ asset('logo3.png') }}" alt="Logo" width="60" height="60" style="border-radius: 50%; display: block; border: 0;">
                        </td>
                    </tr>
                    
                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding: 10px 0;" class="header-padding">
                            <h2 style="color: #ffffff; font-size: 22px; margin: 0; padding: 0 15px;">You've Been Registered!</h2>
                            <p style="color: #ffffff; margin: 5px 0 0 0; padding: 0 15px;">{{ config('app.name') }} Event Registration</p>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td align="center" class="mobile-padding">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#131619" style="border-radius: 15px; margin: 20px 0; width: 100%;">
                                <tr>
                                    <td align="center" style="padding: 30px;" class="content-padding">                                        
                                        <!-- Message -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>
                                                <td align="center" style="color: #ffffff; font-size: 14px; line-height: 1.5; padding-bottom: 25px;">
                                                    <p style="margin: 0 0 10px 0;">Hello {{ $user->user_name }},</p>
                                                    <p style="margin: 0 0 20px 0;">You have been automatically registered for the event: <strong>{{ $event->title }}</strong>.</p>
                                                    
                                                    <div style="text-align: left; margin: 20px 0;">
                                                        <p style="margin: 0 0 10px 0;"><strong>Event Details:</strong></p>
                                                        <ul style="list-style-type: none; padding-left: 0;" class="event-details">
                                                            <li style="margin-bottom: 5px;">• <strong>Start Date:</strong> {{ $event->start_date->format('F j, Y, g:i a') }}</li>
                                                            <li style="margin-bottom: 5px;">• <strong>End Date:</strong> {{ $event->end_date->format('F j, Y, g:i a') }}</li>
                                                        </ul>
                                                    </div>
                                                    
                                                    <p style="margin: 0;">{{ $event->description }}</p>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Button -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="button-container">
                                            <tr>
                                                <td align="center" style="padding: 20px 0;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td bgcolor="#38FFE5" style="padding: 12px 30px; border-radius: 25px;">
                                                                <a href="{{ $eventUrl }}" style="color: #000000; font-weight: bold; text-decoration: none; display: inline-block; font-size: 14px;">View Event</a>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Additional Info -->
                                      
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Social Icons -->
                    <tr>
                        <td align="center" style="padding: 20px 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding: 0 10px;"><a href="" style="text-decoration: none;"><img src="https://cdn-icons-png.flaticon.com/512/5968/5968756.png" alt="Discord" width="30" height="30" class="social-icon" style="display: block; border: 0;"></a></td>
                                    <td style="padding: 0 10px;"><a href="https://x.com/cyberxbytes" style="text-decoration: none;"><img src="https://cdn-icons-png.flaticon.com/512/733/733579.png" alt="X" width="30" height="30" class="social-icon" style="display: block; border: 0;"></a></td>
                                    <td style="padding: 0 10px;"><a href="" style="text-decoration: none;"><img src="https://cdn-icons-png.flaticon.com/512/174/174857.png" alt="LinkedIn" width="30" height="30" class="social-icon" style="display: block; border: 0;"></a></td>
                                    <td style="padding: 0 10px;"><a href="https://t.me/CyberXbytes" style="text-decoration: none;"><img src="https://cdn-icons-png.flaticon.com/512/2111/2111646.png" alt="Telegram" width="30" height="30" class="social-icon" style="display: block; border: 0;"></a></td>
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