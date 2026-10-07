<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KP's Kitchen - Verification Code</title>
    <style>
        body, p, h1, h2, h3, table, td, div, a {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        body {
            background-color: #f4f6f8;
            color: #1f2937;
            padding: 30px 15px;
        }
        .email-wrapper {
            max-width: 540px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
            border: 1px solid #e5e7eb;
        }
        .header-banner {
            background: linear-gradient(135deg, #b91c1c 0%, #7f1d1d 100%);
            background-color: #b91c1c;
            padding: 30px 20px 22px;
            text-align: center;
        }
        .brand-title {
            color: #ffffff;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-family: 'Georgia', 'Times New Roman', serif;
            margin-bottom: 6px;
        }
        .brand-divider {
            border: 0;
            border-top: 1px solid rgba(255, 255, 255, 0.35);
            width: 45px;
            margin: 6px auto 8px;
        }
        .brand-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .content-body {
            padding: 30px 28px 24px;
        }
        .purpose-badge {
            display: inline-block;
            background-color: #ffe4e6;
            color: #be123c;
            font-size: 11px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 14px;
        }
        .greeting-heading {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 12px;
        }
        .intro-text {
            font-size: 14px;
            line-height: 1.6;
            color: #4b5563;
            margin-bottom: 20px;
        }
        .otp-container {
            background-color: #fff8f8;
            border: 2px dashed #fca5a5;
            border-radius: 10px;
            padding: 22px 16px;
            text-align: center;
            margin: 22px 0 24px;
        }
        .otp-label {
            font-size: 11px;
            font-weight: 700;
            color: #991b1b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .otp-digits {
            font-size: 34px;
            font-weight: 800;
            letter-spacing: 8px;
            color: #b91c1c;
            font-family: 'Courier New', Courier, monospace;
            padding-left: 8px;
        }
        .otp-timer {
            font-size: 12px;
            color: #7f1d1d;
            margin-top: 8px;
            font-weight: 500;
        }
        .security-notice {
            background-color: #f9fafb;
            border-left: 4px solid #b91c1c;
            padding: 12px 14px;
            border-radius: 0 6px 6px 0;
            font-size: 12.5px;
            line-height: 1.5;
            color: #4b5563;
            margin-bottom: 20px;
        }
        .signoff-section {
            border-top: 1px solid #f3f4f6;
            padding-top: 16px;
            font-size: 13px;
            line-height: 1.6;
            color: #4b5563;
        }
        .footer-section {
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
            padding: 20px;
            text-align: center;
            font-size: 11px;
            line-height: 1.6;
            color: #6b7280;
        }
        .footer-brand {
            font-weight: 700;
            color: #374151;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <!-- Header Banner -->
        <div class="header-banner">
            <div class="brand-title">KP's Kitchen</div>
            <div class="brand-divider"></div>
            <div class="brand-subtitle">Authentic Indian Tiffin & Catering Service</div>
        </div>

        <!-- Main Content -->
        <div class="content-body">
            @if(isset($purpose) && $purpose === 'password_reset')
                <div class="purpose-badge">Password Reset Request</div>
                <div class="greeting-heading">Reset Your Account Password</div>
                <p class="intro-text">
                    We received a request to reset the password for your KP's Kitchen account. Please use the 6-digit One-Time Password (OTP) below to proceed:
                </p>
            @else
                <div class="purpose-badge">Email Verification</div>
                <div class="greeting-heading">Verify Your Email Address</div>
                <p class="intro-text">
                    Thank you for signing up with KP's Kitchen! Please enter the 6-digit One-Time Password (OTP) below to verify your email and activate your account:
                </p>
            @endif

            <!-- OTP Box -->
            <div class="otp-container">
                <div class="otp-label">One-Time Verification Code</div>
                <div class="otp-digits">{{ $otp }}</div>
                <div class="otp-timer">⏱ Valid for 10 minutes</div>
            </div>

            <!-- Security Callout -->
            <div class="security-notice">
                <strong>Security Tip:</strong> Never share this OTP with anyone. KP's Kitchen staff will never ask you for your verification code. If you did not make this request, you can safely ignore this email.
            </div>

            <!-- Signoff -->
            <div class="signoff-section">
                Warm regards,<br>
                <strong style="color: #111827; font-size: 13.5px;">The KP's Kitchen Team</strong><br>
                <span style="color: #6b7280; font-size: 12px;">Adelaide, South Australia</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-section">
            <div class="footer-brand">KP's Kitchen Restaurant & Tiffin Delivery</div>
            <div>Adelaide, South Australia • Operating in Australian Central Time (ACST / ACDT)</div>
            <div style="margin-top: 6px;">© {{ date('Y') }} KP's Kitchen. All rights reserved.</div>
        </div>
    </div>
</body>
</html>
