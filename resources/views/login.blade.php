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

    <link rel="stylesheet" href="{{ asset('public/assets/css/style.css') }}">

    <script>
        window.AppConfig = {
            baseUrl: "{{ url('/') }}"
        };
    </script>
</head>

<body class="kp_kitchen_admin_panel_auth_body">

    <main class="kp_kitchen_admin_panel_auth_shell">

        <!-- Left / Visual Section -->
        <section class="kp_kitchen_admin_panel_auth_visual" style="justify-content: center; align-items: center;">

            <div class="kp_kitchen_admin_panel_auth_brand" style="text-align: center; z-index: 1;">
                <img src="{{ asset('public/assets/images/logo.png') }}" alt="KP's Kitchen Logo" style="max-width: 280px; height: auto;">
            </div>

        </section>


        <!-- Login Form Section -->
        <section class="kp_kitchen_admin_panel_auth_form_wrap">

            <form id="loginForm" class="kp_kitchen_admin_panel_auth_form" method="POST"
                action="{{ route('login.submit') }}">

                @csrf


                <!-- Form Heading -->
                <div class="kp_kitchen_admin_panel_auth_form_heading">
{{-- 
                    <span class="kp_kitchen_admin_panel_auth_eyebrow">
                        Welcome back
                    </span> --}}

                    <h2 class="kp_kitchen_admin_panel_auth_form_title">
                        Login
                    </h2>
                    {{--
                    <p class="kp_kitchen_admin_panel_auth_form_text">
                        Enter your credentials to access the dashboard.
                    </p> --}}

                </div>


                <!-- Email -->
                <label class="kp_kitchen_admin_panel_form_group">

                    <span class="kp_kitchen_admin_panel_form_label">
                        Email address
                    </span>

                    <input class="kp_kitchen_admin_panel_form_input @error('email') is-invalid @enderror" type="email"
                        id="loginEmail" name="email" placeholder="admin@kpkitchen.com" value="{{ old('email') }}"
                        required autocomplete="email">

                    @error('email')
                        <span class="kp_kitchen_admin_panel_form_error">
                            {{ $message }}
                        </span>
                    @enderror

                </label>


                <!-- Password -->
                <label class="kp_kitchen_admin_panel_form_group">

                    <span class="kp_kitchen_admin_panel_form_label">
                        Password
                    </span>

                    <input class="kp_kitchen_admin_panel_form_input @error('password') is-invalid @enderror"
                        type="password" id="loginPassword" name="password" placeholder="Enter password" required
                        autocomplete="current-password">

                    @error('password')
                        <span class="kp_kitchen_admin_panel_form_error">
                            {{ $message }}
                        </span>
                    @enderror

                </label>


                <!-- Remember / Forgot Password -->
                <div class="kp_kitchen_admin_panel_auth_options">

                    <label class="kp_kitchen_admin_panel_checkbox_label">

                        <input type="checkbox" id="loginRemember" name="remember" value="1"
                            class="kp_kitchen_admin_panel_checkbox" {{ old('remember') ? 'checked' : '' }}>

                        <span class="kp_kitchen_admin_panel_checkbox_text">
                            Remember me
                        </span>

                    </label>


                    @if (Route::has('forgot-password'))

                        <a href="{{ route('forgot-password') }}" class="kp_kitchen_admin_panel_text_button"
                            style="text-decoration: none;">
                            Forgot password?
                        </a>

                    @endif

                </div>


                <!-- General Error Message -->
                @if ($errors->any())

                    <div class="kp_kitchen_admin_panel_form_message">

                        @foreach ($errors->all() as $error)

                            <p>
                                {{ $error }}
                            </p>

                        @endforeach

                    </div>

                @endif


                <!-- Success Message -->
                @if (session('success'))

                    <div class="kp_kitchen_admin_panel_form_message">

                        <p>
                            {{ session('success') }}
                        </p>

                    </div>

                @endif


                <!-- Login Button -->
                <button type="submit" class="kp_kitchen_admin_panel_primary_button kp_kitchen_admin_panel_full_button">
                    Login to Dashboard
                </button>




            </form>

        </section>

    </main>


    <!-- Optional JavaScript -->
    <script>
        window.AppConfig = {
            baseUrl: "{{ url('/') }}",
            csrfToken: "{{ csrf_token() }}"
        };
    </script>

</body>

</html>