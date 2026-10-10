<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to KP's Kitchen</title>
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
            max-width: 600px;
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
            padding: 32px 24px 24px;
            text-align: center;
        }
        .brand-title {
            color: #ffffff !important;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-family: 'Georgia', 'Times New Roman', serif;
            margin-bottom: 8px;
        }
        .brand-divider {
            border: 0;
            border-top: 1px solid rgba(255, 255, 255, 0.35);
            width: 50px;
            margin: 8px auto 10px;
        }
        .brand-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .content-body {
            padding: 32px 30px 24px;
        }
        .welcome-badge {
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
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 16px;
        }
        .intro-text {
            font-size: 14px;
            line-height: 1.65;
            color: #4b5563;
            margin-bottom: 14px;
        }
        .details-card {
            background-color: #fff8f8;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 18px 20px;
            margin: 22px 0 24px;
        }
        .details-card-title {
            font-size: 11.5px;
            font-weight: 800;
            color: #b91c1c;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 14px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .details-table td {
            padding: 6px 0;
            font-size: 13px;
            vertical-align: top;
        }
        .details-label {
            width: 38%;
            color: #6b7280;
            font-weight: 500;
        }
        .details-value {
            width: 62%;
            color: #111827;
            font-weight: 600;
        }
        .features-heading {
            font-size: 11.5px;
            font-weight: 800;
            color: #1f2937;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin: 24px 0 12px;
        }
        .features-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin: 0 -8px 26px;
        }
        .feature-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px 12px;
            text-align: center;
            vertical-align: top;
            width: 33.33%;
        }
        .feature-icon {
            font-size: 20px;
            margin-bottom: 6px;
        }
        .feature-title {
            font-size: 12px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 4px;
        }
        .feature-desc {
            font-size: 11px;
            color: #6b7280;
            line-height: 1.4;
        }
        .cta-container {
            text-align: center;
            margin: 26px 0 20px;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
            background-color: #b91c1c;
            color: #ffffff !important;
            padding: 13px 32px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 6px;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(185, 28, 28, 0.28);
        }
        .cta-direct-link {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 8px;
            word-break: break-all;
        }
        .dietary-callout {
            border-left: 4px solid #b91c1c;
            background-color: #f9fafb;
            padding: 12px 16px;
            border-radius: 0 6px 6px 0;
            margin: 22px 0;
            font-size: 12.5px;
            line-height: 1.55;
            color: #4b5563;
        }
        .signoff-section {
            font-size: 13px;
            line-height: 1.6;
            color: #4b5563;
            margin-top: 20px;
        }
        .footer-section {
            background-color: #fafafa;
            border-top: 1px solid #e5e7eb;
            padding: 24px 20px;
            text-align: center;
            font-size: 11.5px;
            line-height: 1.6;
            color: #9ca3af;
        }
        .footer-brand {
            font-weight: 700;
            color: #6b7280;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <!-- Header Banner -->
        <div class="header-banner">
            <div class="brand-title">KP'S KITCHEN</div>
            <hr class="brand-divider">
            <div class="brand-subtitle">AUTHENTIC INDIAN TIFFINS & MEALS • ADELAIDE, AUSTRALIA</div>
        </div>

        <!-- Body Content -->
        <div class="content-body">
            <div class="welcome-badge">✨ WELCOME TO THE FAMILY</div>
            <h2 class="greeting-heading">Hello {{ $firstName }},</h2>

            <p class="intro-text">
                Thank you for registering with <strong>KP's Kitchen</strong>! We are absolutely delighted to welcome you to our community of authentic Indian food lovers across Adelaide.
            </p>

            <p class="intro-text">
                From fresh home-style rotis, theplas, and aromatic rice to slow-simmered rich curries, fragrant daals, and daily chef specials, every dish in our kitchen is prepared fresh to order using time-honoured culinary traditions and freshly ground spices.
            </p>

            <!-- Registered Account Details Card -->
            <div class="details-card">
                <div class="details-card-title">📋 YOUR REGISTERED ACCOUNT DETAILS</div>
                <table class="details-table">
                    <tr>
                        <td class="details-label">Full Name:</td>
                        <td class="details-value">{{ $customer->name }}</td>
                    </tr>
                    <tr>
                        <td class="details-label">Email Address:</td>
                        <td class="details-value">
                            <a href="mailto:{{ $customer->email }}" style="color: #1d4ed8; text-decoration: none;">{{ $customer->email }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="details-label">Contact Number:</td>
                        <td class="details-value">
                            <a href="tel:{{ $customer->phone }}" style="color: #1d4ed8; text-decoration: none;">{{ $customer->phone }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="details-label">Delivery Suburb:</td>
                        <td class="details-value">{{ $deliverySuburb }}</td>
                    </tr>
                    <tr>
                        <td class="details-label">Full Address:</td>
                        <td class="details-value" style="font-weight: 500; color: #374151;">{{ $fullAddress }}</td>
                    </tr>
                    <tr>
                        <td class="details-label">Registered Time:</td>
                        <td class="details-value" style="color: #991b1b; font-weight: 700;">{{ $registeredTime }}</td>
                    </tr>
                    <tr>
                        <td class="details-label">Account Status:</td>
                        <td class="details-value" style="color: #15803d; font-weight: 700;">✅ Active & Verified</td>
                    </tr>
                </table>
            </div>

            <!-- Why You Will Love Ordering With Us -->
            <div class="features-heading">WHY YOU WILL LOVE ORDERING WITH US</div>
            <table class="features-table" role="presentation">
                <tr>
                    <td class="feature-box">
                        <div class="feature-icon">🍲</div>
                        <div class="feature-title">Authentic Recipes</div>
                        <div class="feature-desc">Traditional home-style cooking and hand-ground spice blends.</div>
                    </td>
                    <td class="feature-box">
                        <div class="feature-icon">🚚</div>
                        <div class="feature-title">Adelaide Fast Delivery</div>
                        <div class="feature-desc">Packed fresh in insulated packaging direct to your doorstep.</div>
                    </td>
                    {{-- <td class="feature-box">
                        <div class="feature-icon">📱</div>
                        <div class="feature-title">Live Order Tracking</div>
                        <div class="feature-desc">Track kitchen prep and driver dispatch in real-time.</div>
                    </td> --}}
                </tr>
            </table>

            <!-- CTA Button -->
            {{-- <div class="cta-container">
                <a href="{{ $loginUrl }}" class="cta-button" target="_blank">Log In & Explore Our Menu →</a>
                <div class="cta-direct-link">
                    Direct link: <a href="{{ $loginUrl }}" style="color: #b91c1c; text-decoration: underline;">{{ $loginUrl }}</a>
                </div>
            </div> --}}

            <!-- Special Dietary Callout -->
            <div class="dietary-callout">
                <strong>Special Dietary Needs?</strong> Whether you need pure vegetarian, vegan, Jain options, mild spices for kids, or custom catering for your gatherings, please feel free to reach out to our kitchen team.
            </div>

            <!-- Signoff Section -->
            <div class="signoff-section">
                Warm regards & happy dining,<br>
                <strong style="color: #111827; font-size: 14px;">The KP's Kitchen Team</strong><br>
                <span style="color: #6b7280; font-size: 12px;">Adelaide, South Australia, Australia</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-section">
            <div class="footer-brand">KP's Kitchen Restaurant & Tiffin Delivery Portal</div>
            <div>Adelaide, Australia • Operating in Australian Central Time (ACST / ACDT)</div>
            <div style="margin-top: 6px;">
                This welcome email was sent because an account was registered with
                <a href="mailto:{{ $customer->email }}" style="color: #1d4ed8; text-decoration: none;">{{ $customer->email }}</a>.
            </div>
            <div>If you did not create this account, please contact our support team immediately.</div>
            <div style="margin-top: 8px;">© {{ date('Y') }} KP's Kitchen. All rights reserved.</div>
        </div>
    </div>
</body>
</html>
