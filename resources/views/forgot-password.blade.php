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
  <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
  <script>
    window.AppConfig = {
      baseUrl: "{{ url('/') }}"
    };
  </script>
  <style>
    /* Styling to manage steps visibility transition */
    .otp_step {
      display: none;
    }
    .otp_step_active {
      display: block;
    }
  </style>
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
        <!-- STEP 1: Enter Email -->
        <div id="step1" class="otp_step otp_step_active">
          <div class="kp_kitchen_admin_panel_auth_form_heading">
            <span class="kp_kitchen_admin_panel_auth_eyebrow">Password Recovery</span>
            <h2 class="kp_kitchen_admin_panel_auth_form_title">Forgot Password</h2>
            <p class="kp_kitchen_admin_panel_auth_form_text">Enter your email and we'll send you an OTP code to verify your account.</p>
          </div>

          <form id="emailForm">
            <label class="kp_kitchen_admin_panel_form_group">
              <span class="kp_kitchen_admin_panel_form_label">Email address</span>
              <input class="kp_kitchen_admin_panel_form_input" type="email" id="resetEmail" placeholder="admin@kpkitchen.com" required>
            </label>

            <p id="emailMessage" class="kp_kitchen_admin_panel_form_message"></p>
            <button type="submit" class="kp_kitchen_admin_panel_primary_button kp_kitchen_admin_panel_full_button">Send Verification OTP</button>
          </form>
        </div>

        <!-- STEP 2: Enter OTP Code -->
        <div id="step2" class="otp_step">
          <div class="kp_kitchen_admin_panel_auth_form_heading">
            <span class="kp_kitchen_admin_panel_auth_eyebrow">OTP Verification</span>
            <h2 class="kp_kitchen_admin_panel_auth_form_title">Verify Email</h2>
            <p class="kp_kitchen_admin_panel_auth_form_text">Enter the 6-digit One-Time Password (OTP) sent to <strong id="sentEmailPlaceholder"></strong>.</p>
          </div>

          <form id="otpForm">
            <label class="kp_kitchen_admin_panel_form_group">
              <span class="kp_kitchen_admin_panel_form_label">Verification Code (OTP)</span>
              <input class="kp_kitchen_admin_panel_form_input" type="text" id="resetOtp" minlength="6" maxlength="6" placeholder="Enter 6-digit code" required style="letter-spacing: 4px; text-align: center; font-weight: 700; font-size: 1.2rem;">
            </label>

            <p id="otpMessage" class="kp_kitchen_admin_panel_form_message"></p>
            <button type="submit" class="kp_kitchen_admin_panel_primary_button kp_kitchen_admin_panel_full_button">Verify Code</button>
            <button type="button" id="resendButton" class="kp_kitchen_admin_panel_text_button" style="margin-top: 16px; width: 100%; text-align: center;">Resend OTP Code</button>
          </form>
        </div>

        <!-- STEP 3: Change Password -->
        <div id="step3" class="otp_step">
          <div class="kp_kitchen_admin_panel_auth_form_heading">
            <span class="kp_kitchen_admin_panel_auth_eyebrow">Reset Credentials</span>
            <h2 class="kp_kitchen_admin_panel_auth_form_title">New Password</h2>
            <p class="kp_kitchen_admin_panel_auth_form_text">Please choose a secure new password for your account.</p>
          </div>

          <form id="passwordForm">
            <label class="kp_kitchen_admin_panel_form_group">
              <span class="kp_kitchen_admin_panel_form_label">New password</span>
              <input class="kp_kitchen_admin_panel_form_input" type="password" id="newPassword" minlength="6" placeholder="Minimum 6 characters" required>
            </label>

            <label class="kp_kitchen_admin_panel_form_group">
              <span class="kp_kitchen_admin_panel_form_label">Confirm new password</span>
              <input class="kp_kitchen_admin_panel_form_input" type="password" id="confirmNewPassword" minlength="6" placeholder="Confirm new password" required>
            </label>

            <p id="passwordMessage" class="kp_kitchen_admin_panel_form_message"></p>
            <button type="submit" class="kp_kitchen_admin_panel_primary_button kp_kitchen_admin_panel_full_button">Reset Password</button>
          </form>
        </div>

        <p class="kp_kitchen_admin_panel_auth_switch" style="margin-top: 24px;">Remember your credentials? <a class="kp_kitchen_admin_panel_auth_link" href="{{ route('login') }}">Back to Login</a></p>
      </div>
    </section>
  </main>

  <script>
    (function () {
      const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      const getBaseUrl = () => (window.AppConfig && window.AppConfig.baseUrl) ? window.AppConfig.baseUrl : '';
      
      let emailAddress = '';
      let verificationOtp = '';

      // --- Helpers to switch steps ---
      const showStep = (stepNumber) => {
        document.querySelectorAll('.otp_step').forEach(el => el.classList.remove('otp_step_active'));
        document.getElementById('step' + stepNumber).classList.add('otp_step_active');
      };

      // --- STEP 1: Submit Email ---
      const emailForm = document.getElementById('emailForm');
      emailForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const email = document.getElementById('resetEmail').value.trim();
        const message = document.getElementById('emailMessage');

        message.textContent = '';
        message.className = 'kp_kitchen_admin_panel_form_message';

        try {
          const response = await fetch(getBaseUrl() + '/api/auth/forget-password', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({ email })
          });
          const result = await response.json();

          if (!result.success) {
            message.textContent = result.message || 'Error occurred.';
            message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
            return;
          }

          emailAddress = email;
          document.getElementById('sentEmailPlaceholder').textContent = email;
          
          message.textContent = result.message;
          message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_success';

          setTimeout(() => {
            showStep(2);
            message.textContent = '';
          }, 1500);

        } catch (err) {
          message.textContent = 'Connection error. Please try again.';
          message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
        }
      });

      // --- STEP 2: Verify OTP ---
      const otpForm = document.getElementById('otpForm');
      otpForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const otp = document.getElementById('resetOtp').value.trim();
        const message = document.getElementById('otpMessage');

        message.textContent = '';
        message.className = 'kp_kitchen_admin_panel_form_message';

        try {
          const response = await fetch(getBaseUrl() + '/api/auth/verify-otp', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({ email: emailAddress, otp })
          });
          const result = await response.json();

          if (!result.success) {
            message.textContent = result.message || 'Verification failed.';
            message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
            return;
          }

          verificationOtp = otp;
          message.textContent = result.message;
          message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_success';

          setTimeout(() => {
            showStep(3);
            message.textContent = '';
          }, 1500);

        } catch (err) {
          message.textContent = 'Connection error. Please try again.';
          message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
        }
      });

      // Resend OTP trigger
      document.getElementById('resendButton').addEventListener('click', async () => {
        const message = document.getElementById('otpMessage');
        message.textContent = 'Requesting new OTP code...';
        message.className = 'kp_kitchen_admin_panel_form_message';

        try {
          const response = await fetch(getBaseUrl() + '/api/auth/forget-password', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({ email: emailAddress })
          });
          const result = await response.json();

          if (result.success) {
            message.textContent = 'New OTP code sent successfully.';
            message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_success';
          } else {
            message.textContent = result.message || 'Failed to resend.';
            message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
          }
        } catch (err) {
          message.textContent = 'Failed to resend. Please try again.';
          message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
        }
      });

      // --- STEP 3: Reset Password ---
      const passwordForm = document.getElementById('passwordForm');
      passwordForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const password = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmNewPassword').value;
        const message = document.getElementById('passwordMessage');

        message.textContent = '';
        message.className = 'kp_kitchen_admin_panel_form_message';

        if (password !== confirmPassword) {
          message.textContent = 'Passwords do not match.';
          message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
          return;
        }

        try {
          const response = await fetch(getBaseUrl() + '/api/auth/reset-password', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({
              email: emailAddress,
              otp: verificationOtp,
              password: password,
              confirm_password: confirmPassword
            })
          });
          const result = await response.json();

          if (!result.success) {
            message.textContent = result.message || 'Password reset failed.';
            message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
            return;
          }

          message.textContent = result.message;
          message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_success';

          setTimeout(() => {
            window.location.href = './login';
          }, 1500);

        } catch (err) {
          message.textContent = 'Connection error. Please try again.';
          message.className = 'kp_kitchen_admin_panel_form_message kp_kitchen_admin_panel_form_message_error';
        }
      });
    })();
  </script>
</body>
</html>
