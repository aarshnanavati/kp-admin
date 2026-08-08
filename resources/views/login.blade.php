<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>KP Kitchen Admin - Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
</head>
<body class="kp_kitchen_admin_panel_auth_body">
  <main class="kp_kitchen_admin_panel_auth_shell">
    <section class="kp_kitchen_admin_panel_auth_visual">
      <div class="kp_kitchen_admin_panel_auth_brand">KP Kitchen</div>
      <div class="kp_kitchen_admin_panel_auth_visual_content">
        <span class="kp_kitchen_admin_panel_auth_badge">Tiffin Management System</span>
        <h1 class="kp_kitchen_admin_panel_auth_title">Manage every meal, driver and delivery from one place.</h1>
        <p class="kp_kitchen_admin_panel_auth_text">A clean and responsive admin panel for your tiffin ordering and delivery operation.</p>
      </div>
    </section>

    <section class="kp_kitchen_admin_panel_auth_form_wrap">
      <form id="loginForm" class="kp_kitchen_admin_panel_auth_form">
        <div class="kp_kitchen_admin_panel_auth_form_heading">
          <span class="kp_kitchen_admin_panel_auth_eyebrow">Welcome back</span>
          <h2 class="kp_kitchen_admin_panel_auth_form_title">Admin Login</h2>
          <p class="kp_kitchen_admin_panel_auth_form_text">Enter your credentials to access the dashboard.</p>
        </div>

        <label class="kp_kitchen_admin_panel_form_group">
          <span class="kp_kitchen_admin_panel_form_label">Email address</span>
          <input class="kp_kitchen_admin_panel_form_input" type="email" id="loginEmail" placeholder="admin@kpkitchen.com" required>
        </label>

        <label class="kp_kitchen_admin_panel_form_group">
          <span class="kp_kitchen_admin_panel_form_label">Password</span>
          <input class="kp_kitchen_admin_panel_form_input" type="password" id="loginPassword" placeholder="Enter password" required>
        </label>

        <div class="kp_kitchen_admin_panel_auth_options">
          <label class="kp_kitchen_admin_panel_checkbox_label">
            <input type="checkbox" id="loginRemember" class="kp_kitchen_admin_panel_checkbox">
            <span class="kp_kitchen_admin_panel_checkbox_text">Remember me</span>
          </label>
          <a href="{{ route('forgot-password') }}" class="kp_kitchen_admin_panel_text_button" style="text-decoration: none;">Forgot password?</a>
        </div>

        <p id="loginMessage" class="kp_kitchen_admin_panel_form_message"></p>
        <button type="submit" class="kp_kitchen_admin_panel_primary_button kp_kitchen_admin_panel_full_button">Login to Dashboard</button>

        <p class="kp_kitchen_admin_panel_auth_switch">New admin? <a class="kp_kitchen_admin_panel_auth_link" href="{{ route('register') }}">Create an account</a></p>
        <p class="kp_kitchen_admin_panel_demo_note">Demo login: admin@kpkitchen.com / admin123</p>
      </form>
    </section>
  </main>
  <script src="{{ asset('assets/js/auth.js') }}"></script>
</body>
</html>
