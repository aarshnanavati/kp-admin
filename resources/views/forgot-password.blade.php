<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>KP Kitchen Admin - Reset Password</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('public/assets/css/style.css') }}">
  <script>
    window.AppConfig = {
      baseUrl: "{{ url('/') }}"
    };
  </script>
</head>
<body class="kp_kitchen_admin_panel_auth_body">
  <main class="kp_kitchen_admin_panel_auth_shell">
    <section class="kp_kitchen_admin_panel_auth_visual">
      <div class="kp_kitchen_admin_panel_auth_brand">KP Kitchen</div>
      <div class="kp_kitchen_admin_panel_auth_visual_content">
        <span class="kp_kitchen_admin_panel_auth_badge">Security Recovery</span>
        <h1 class="kp_kitchen_admin_panel_auth_title">Recover your administrator account securely.</h1>
        <p class="kp_kitchen_admin_panel_auth_text">We will verify your identity via a 6-digit One-Time Password sent to your registered email before resetting your password.</p>
      </div>
    </section>

    <section class="kp_kitchen_admin_panel_auth_form_wrap">
      <div class="kp_kitchen_admin_panel_auth_form">
        
        @if (!session('reset_step'))
          <!-- STEP 1: Enter Email -->
          <div>
            <div class="kp_kitchen_admin_panel_auth_form_heading">
              <span class="kp_kitchen_admin_panel_auth_eyebrow">Password Recovery</span>
              <h2 class="kp_kitchen_admin_panel_auth_form_title">Forgot Password</h2>
              <p class="kp_kitchen_admin_panel_auth_form_text">Enter your email and we'll send you an OTP code to verify your account.</p>
            </div>

            <form id="emailForm" method="POST" action="{{ route('forgot-password.send-otp') }}">
              @csrf
              <label class="kp_kitchen_admin_panel_form_group">
                <span class="kp_kitchen_admin_panel_form_label">Email address</span>
                <input class="kp_kitchen_admin_panel_form_input @error('email') is-invalid @enderror" type="email" id="resetEmail" name="email" value="{{ old('email') }}" placeholder="admin@kpkitchen.com" required>
                @error('email')
                  <span class="kp_kitchen_admin_panel_form_error">{{ $message }}</span>
                @enderror
              </label>

              @if ($errors->any())
                <div class="kp_kitchen_admin_panel_form_message">
                  @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                  @endforeach
                </div>
              @endif

              <button type="submit" class="kp_kitchen_admin_panel_primary_button kp_kitchen_admin_panel_full_button">Send Verification OTP</button>
            </form>
          </div>

        @elseif (session('reset_step') === 'verify')
          <!-- STEP 2: Enter OTP Code -->
          <div>
            <div class="kp_kitchen_admin_panel_auth_form_heading">
              <span class="kp_kitchen_admin_panel_auth_eyebrow">OTP Verification</span>
              <h2 class="kp_kitchen_admin_panel_auth_form_title">Verify Email</h2>
              <p class="kp_kitchen_admin_panel_auth_form_text">Enter the 6-digit One-Time Password (OTP) sent to <strong>{{ session('reset_email') }}</strong>.</p>
            </div>

            <form id="otpForm" method="POST" action="{{ route('forgot-password.verify-otp') }}">
              @csrf
              <label class="kp_kitchen_admin_panel_form_group">
                <span class="kp_kitchen_admin_panel_form_label">Verification Code (OTP)</span>
                <input class="kp_kitchen_admin_panel_form_input @error('otp') is-invalid @enderror" type="text" id="resetOtp" name="otp" minlength="6" maxlength="6" placeholder="Enter 6-digit code" required style="letter-spacing: 4px; text-align: center; font-weight: 700; font-size: 1.2rem;">
                @error('otp')
                  <span class="kp_kitchen_admin_panel_form_error">{{ $message }}</span>
                @enderror
              </label>

              @if ($errors->any())
                <div class="kp_kitchen_admin_panel_form_message">
                  @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                  @endforeach
                </div>
              @endif

              @if (session('success'))
                <div class="kp_kitchen_admin_panel_form_message">
                  <p>{{ session('success') }}</p>
                </div>
              @endif

              <button type="submit" class="kp_kitchen_admin_panel_primary_button kp_kitchen_admin_panel_full_button">Verify Code</button>
            </form>

            <form method="POST" action="{{ route('forgot-password.send-otp') }}" style="margin-top: 16px;">
              @csrf
              <input type="hidden" name="email" value="{{ session('reset_email') }}">
              <button type="submit" class="kp_kitchen_admin_panel_text_button" style="width: 100%; text-align: center;">Resend OTP Code</button>
            </form>
          </div>

        @elseif (session('reset_step') === 'reset')
          <!-- STEP 3: Change Password -->
          <div>
            <div class="kp_kitchen_admin_panel_auth_form_heading">
              <span class="kp_kitchen_admin_panel_auth_eyebrow">Reset Credentials</span>
              <h2 class="kp_kitchen_admin_panel_auth_form_title">New Password</h2>
              <p class="kp_kitchen_admin_panel_auth_form_text">Please choose a secure new password for your account.</p>
            </div>

            <form id="passwordForm" method="POST" action="{{ route('forgot-password.reset') }}">
              @csrf
              <label class="kp_kitchen_admin_panel_form_group">
                <span class="kp_kitchen_admin_panel_form_label">New password</span>
                <input class="kp_kitchen_admin_panel_form_input @error('password') is-invalid @enderror" type="password" id="newPassword" name="password" minlength="6" placeholder="Minimum 6 characters" required>
                @error('password')
                  <span class="kp_kitchen_admin_panel_form_error">{{ $message }}</span>
                @enderror
              </label>

              <label class="kp_kitchen_admin_panel_form_group">
                <span class="kp_kitchen_admin_panel_form_label">Confirm new password</span>
                <input class="kp_kitchen_admin_panel_form_input @error('confirm_password') is-invalid @enderror" type="password" id="confirmNewPassword" name="confirm_password" minlength="6" placeholder="Confirm new password" required>
                @error('confirm_password')
                  <span class="kp_kitchen_admin_panel_form_error">{{ $message }}</span>
                @enderror
              </label>

              @if ($errors->any())
                <div class="kp_kitchen_admin_panel_form_message">
                  @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                  @endforeach
                </div>
              @endif

              @if (session('success'))
                <div class="kp_kitchen_admin_panel_form_message">
                  <p>{{ session('success') }}</p>
                </div>
              @endif

              <button type="submit" class="kp_kitchen_admin_panel_primary_button kp_kitchen_admin_panel_full_button">Reset Password</button>
            </form>
          </div>
        @endif

        <p class="kp_kitchen_admin_panel_auth_switch" style="margin-top: 24px;">Remember your credentials? <a class="kp_kitchen_admin_panel_auth_link" href="{{ route('login') }}">Back to Login</a></p>
      </div>
    </section>
  </main>
</body>
</html>
