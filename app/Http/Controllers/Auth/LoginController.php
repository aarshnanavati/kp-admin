<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PasswordOtp;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;
use App\Mail\KitchenAlertMail;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLogin()
    {
        return view('login');
    }

    /**
     * Handle login submit.
     */
    public function loginSubmit(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = strtolower(trim($request->email));
        $remember = $request->has('remember');

        if (Auth::attempt(['email' => $email, 'password' => $request->password], $remember)) {
            $request->session()->regenerate();
            
            $user = Auth::user();
            try {
                Mail::to('admin@kpkitchen.com')->send(new KitchenAlertMail("User Login Notification", "{$user->name} has logged in into KP's Kitchen."));
            } catch (\Exception $e) {
                Log::warning("Web login alert email failed: " . $e->getMessage());
            }

            return redirect()->intended('/')->with('success', 'Welcome back to the KP Kitchen Admin Panel!');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Show the registration form.
     */
    public function showRegister()
    {
        return view('register');
    }

    /**
     * Handle registration submit.
     */
    public function registerSubmit(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|same:password',
        ], [
            'confirm_password.same' => 'Passwords do not match.',
        ]);

        $user = User::create([
            'name' => trim($request->name),
            'email' => strtolower(trim($request->email)),
            'password' => Hash::make($request->password),
            'user_type' => 'admin',
        ]);

        try {
            Mail::to($user->email)->send(new KitchenAlertMail("Welcome to KP's Kitchen Admin Panel!", "Thank you {$user->name} for registering in KP's Kitchen admin team!"));
        } catch (\Exception $e) {
            Log::warning("Web admin welcome email failed: " . $e->getMessage());
        }

        return redirect()->route('login')->with('success', 'Admin registration successful. You can now log in.');
    }

    /**
     * Show the password recovery form.
     */
    public function showForgotPassword()
    {
        return view('forgot-password');
    }

    /**
     * Step 1: Send OTP to email.
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($request->email));
        if (!User::where('email', $email)->exists()) {
            return back()->withErrors([
                'email' => 'No admin account found with that email address.',
            ])->onlyInput('email');
        }

        // Generate 6-digit OTP
        $otp = (string)rand(100000, 999999);

        // Save OTP
        PasswordOtp::where('email', $email)->delete();
        PasswordOtp::create([
            'email' => $email,
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        Log::info("Web Admin Password Reset OTP for {$email}: {$otp}");

        try {
            Mail::to($email)->send(new SendOtpMail($otp));
        } catch (\Exception $e) {
            Log::warning("SMTP email sending failed. Fallback logged in laravel.log.");
        }

        session(['reset_email' => $email, 'reset_step' => 'verify']);

        return redirect()->route('forgot-password')->with('success', 'A verification OTP has been sent to your email.');
    }

    /**
     * Step 2: Verify OTP.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $email = session('reset_email');
        if (!$email) {
            return redirect()->route('forgot-password')->withErrors(['email' => 'Session expired. Please request a new OTP.']);
        }

        $otpRecord = PasswordOtp::where('email', $email)
            ->where('otp', trim($request->otp))
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otpRecord) {
            return back()->withErrors([
                'otp' => 'Invalid or expired OTP. Please check and try again.',
            ]);
        }

        session(['otp_verified' => true, 'reset_step' => 'reset', 'reset_otp' => trim($request->otp)]);

        return redirect()->route('forgot-password')->with('success', 'OTP verified successfully. You can now reset your password.');
    }

    /**
     * Step 3: Reset password.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|same:password',
        ], [
            'confirm_password.same' => 'Passwords do not match.',
        ]);

        $email = session('reset_email');
        $otp = session('reset_otp');
        $verified = session('otp_verified');

        if (!$email || !$otp || !$verified) {
            return redirect()->route('forgot-password')->withErrors(['email' => 'Session invalid. Please request a new OTP.']);
        }

        // Re-verify OTP record
        $otpRecord = PasswordOtp::where('email', $email)
            ->where('otp', $otp)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otpRecord) {
            return redirect()->route('forgot-password')->withErrors(['email' => 'OTP expired. Please try again.']);
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $user->update([
                'password' => Hash::make($request->password),
            ]);
        }

        // Cleanup
        $otpRecord->delete();
        $request->session()->forget(['reset_email', 'reset_step', 'reset_otp', 'otp_verified']);

        return redirect()->route('login')->with('success', 'Password reset successful. Please log in with your new password.');
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Logged out successfully.');
    }
}
