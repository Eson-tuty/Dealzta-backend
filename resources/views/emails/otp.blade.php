<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Code</title>
</head>

<body style="margin:0; padding:0; background:#f4f4f4; font-family:Arial, sans-serif;">

    <table align="center" cellpadding="0" cellspacing="0" width="100%" style="background:#f4f4f4; padding:40px 0;">
        <tr>
            <td align="center">

                <!-- MAIN CONTAINER -->
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden;">

                    <!-- HEADER -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #9051c3 0%, #7a3fa8 100%); padding:30px 20px; text-align:center; color:#fff;">

                            <table align="center" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <!-- FIXED LOGO PATH -->
                                        <img src="https://api.dealzta.com/storage/profiles/logo.png" width="40" height="40"
                                            style="display:block; border-radius:8px;" alt="Dealzta Logo">
                                    </td>

                                    <td style="padding-left:10px;">
                                        <span style="font-size:28px; font-weight:bold; color:#fff;">Dealzta</span>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:10px 0 0 0; font-size:18px;">Verification Code</p>

                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding:36px 28px; text-align:center;">

                            <div style="font-size:16px; color:#666; margin-bottom:10px;">Your verification code is:</div>

                            <!-- OTP ROW -->
                            <table align="center" cellpadding="0" cellspacing="10">
                                <tr>
                                    @php $digits = str_split($otp); @endphp

                                    @foreach($digits as $d)
                                    <td>

                                        <!-- 3D FLIP TILE (DIGIT SHOWN ONCE ONLY) -->
                                        <table cellpadding="0" cellspacing="0" width="56" style="border-radius:8px; overflow:hidden; background:#0b0b0b; border:2px solid #9051c3;">
                                            
                                            <!-- TOP HALF -->
                                            <tr>
                                                <td height="40" style="
                                                    background: linear-gradient(#1b1b1b,#0f0f0f);
                                                    border-bottom:1px solid rgba(255,255,255,0.06);
                                                    text-align:center;
                                                    font-size:24px;
                                                    font-family:'Courier New', monospace;
                                                    color:#fff;
                                                    font-weight:700;">
                                                    {{ $d }}
                                                </td>
                                            </tr>

                                            <!-- BOTTOM HALF (EMPTY SO NOT REPEAT) -->
                                            <tr>
                                                <td height="40" style="
                                                    background: linear-gradient(#070707,#000000);
                                                    box-shadow: inset 0 6px 12px rgba(0,0,0,0.6);
                                                    text-align:center;
                                                    font-size:24px;
                                                    font-family:'Courier New', monospace;
                                                    color:transparent;">
                                                    {{ $d }}
                                                </td>
                                            </tr>

                                        </table>

                                    </td>
                                    @endforeach
                                </tr>
                            </table>

                            <div style="font-size:15px; color:#444; margin-top:10px;">
                                This code will expire in <strong>5 minutes</strong>.
                            </div>

                            <div style="color:#ff4d4d; font-size:13px; margin-top:15px;">
                                ⚠️ Never share this code with anyone.
                            </div>

                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="background:#f8f9fa; padding:20px; text-align:center; font-size:14px; color:#999;">
                            <p>If you didn't request this code, please ignore this email.</p>
                            <p>&copy; {{ date('Y') }} Dealzta. All rights reserved.</p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>

</html>
