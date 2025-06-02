<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Challenge Status Update</title>
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
            .status-badge {
                padding: 8px 12px !important;
                font-size: 14px !important;
            }
            .social-icon {
                width: 25px !important;
                height: 25px !important;
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
                            <img src="{{ url('logo3.png') }}" alt="Logo" width="60" height="60" style="border-radius: 50%; display: block; border: 0;">
                        </td>
                    </tr>
                    
                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding: 10px 0;" class="header-padding">
                            <h2 style="color: #ffffff; font-size: 22px; margin: 0; padding: 0 15px;">Challenge Status Update</h2>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td align="center" class="mobile-padding">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#131619" style="border-radius: 15px; margin: 20px 0; width: 100%;">
                                <tr>
                                    <td align="center" style="padding: 30px;" class="content-padding">
                                        <!-- Profile Image -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" style="padding-bottom: 30px;">
                                                    <img src="{{ $user->profile_image ?? url('person.png') }}" alt="Profile" width="80" height="80" style="border-radius: 50%; object-fit: cover; display: block; border: 0;">
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Message -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>
                                                <td align="center" style="color: #ffffff; font-size: 14px; line-height: 1.5; padding-bottom: 25px;">
                                                    <p style="margin: 0;">Hello {{ $user->user_name }},</p>
                                                    <p style="margin: 10px 0 0 0;">Your challenge status has been updated.</p>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Challenge Details -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" bgcolor="#1e2124" style="border-radius: 15px; margin: 20px 0;">
                                            <tr>
                                                <td align="center" style="padding: 20px;">
                                                    <h3 style="color: #ffffff; margin: 0 0 15px 0;">Challenge Details</h3>
                                                    <p style="color: #ffffff; margin: 0 0 10px 0;">Name: {{ $challenge->name }}</p>
                                                    <p style="color: #ffffff; margin: 0 0 10px 0;">Category: {{ $challenge->category->name }}</p>
                                                    <p style="color: #ffffff; margin: 0 0 10px 0;">Difficulty: {{ ucfirst($challenge->difficulty) }}</p>
                                                    
                                                    <!-- Status Badge -->
                                                    <div style="margin: 20px 0;">
                                                        <span class="status-badge" style="background-color: {{ $challenge->status === 'approved' ? '#28a745' : ($challenge->status === 'declined' ? '#dc3545' : '#ffc107') }}; color: #ffffff; padding: 10px 20px; border-radius: 20px; font-size: 16px; font-weight: bold;">
                                                            {{ ucfirst(str_replace('_', ' ', $challenge->status)) }}
                                                        </span>
                                                    </div>
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
                            <p style="margin: 5px 0; text-align: center;">If you did not create this challenge, no further action is required.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html> 