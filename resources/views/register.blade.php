<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>KP Kitchen Admin - Register</title>
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
        <span class="kp_kitchen_admin_panel_auth_badge">Admin Registration</span>
        <h1 class="kp_kitchen_admin_panel_auth_title">Create your administrator account.</h1>
        <p class="kp_kitchen_admin_panel_auth_text">Register an admin profile and begin managing tiffins, orders, drivers and billing.</p>
      </div>
    </section>

    <section class="kp_kitchen_admin_panel_auth_form_wrap">
      <form id="registerForm" class="kp_kitchen_admin_panel_auth_form" method="POST" action="{{ route('register.submit') }}">
        @csrf

        <div class="kp_kitchen_admin_panel_auth_form_heading">
          <span class="kp_kitchen_admin_panel_auth_eyebrow">Get started</span>
          <h2 class="kp_kitchen_admin_panel_auth_form_title">Create Account</h2>
        </div>

        <!-- Name -->
        <label class="kp_kitchen_admin_panel_form_group">
          <span class="kp_kitchen_admin_panel_form_label">Full name</span>
          <input class="kp_kitchen_admin_panel_form_input @error('name') is-invalid @enderror" type="text" id="registerName" name="name" placeholder="Admin name" value="{{ old('name') }}" required>
          @error('name')
            <span class="kp_kitchen_admin_panel_form_error">{{ $message }}</span>
          @enderror
        </label>

        <!-- Email -->
        <label class="kp_kitchen_admin_panel_form_group">
          <span class="kp_kitchen_admin_panel_form_label">Email address</span>
          <input class="kp_kitchen_admin_panel_form_input @error('email') is-invalid @enderror" type="email" id="registerEmail" name="email" placeholder="admin@example.com" value="{{ old('email') }}" required>
          @error('email')
            <span class="kp_kitchen_admin_panel_form_error">{{ $message }}</span>
          @enderror
        </label>

        <!-- Password -->
        <label class="kp_kitchen_admin_panel_form_group">
          <span class="kp_kitchen_admin_panel_form_label">Password</span>
          <input class="kp_kitchen_admin_panel_form_input @error('password') is-invalid @enderror" type="password" id="registerPassword" name="password" minlength="6" placeholder="Minimum 6 characters" required>
          @error('password')
            <span class="kp_kitchen_admin_panel_form_error">{{ $message }}</span>
          @enderror
        </label>

        <!-- Confirm Password -->
        <label class="kp_kitchen_admin_panel_form_group">
          <span class="kp_kitchen_admin_panel_form_label">Confirm password</span>
          <input class="kp_kitchen_admin_panel_form_input @error('confirm_password') is-invalid @enderror" type="password" id="registerConfirmPassword" name="confirm_password" minlength="6" placeholder="Confirm password" required>
          @error('confirm_password')
            <span class="kp_kitchen_admin_panel_form_error">{{ $message }}</span>
          @enderror
        </label>

        <!-- Errors / Success Alert -->
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

        <button type="submit" class="kp_kitchen_admin_panel_primary_button kp_kitchen_admin_panel_full_button">Create Admin Account</button>
        <p class="kp_kitchen_admin_panel_auth_switch">Already registered? <a class="kp_kitchen_admin_panel_auth_link" href="{{ route('login') }}">Login here</a></p>
      </form>
    </section>
  </main>
</body>
</html>
