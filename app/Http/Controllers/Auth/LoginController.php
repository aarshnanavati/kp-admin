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
     * Show the password recovery form.
     */
    public function showForgotPassword()
    {
        return view('forgot-password');
    }

    /**
     * Safely issue an OTP, compatible with both updated and legacy PasswordOtp models.
     */
    protected function issueOtp(string $email, int $minutes = 10): string
    {
        try {
            if (method_exists(PasswordOtp::class, 'issue')) {
                return PasswordOtp::issue($email, $minutes);
            }
        } catch (\Throwable $e) {
            Log::warning('PasswordOtp::issue failed in LoginController, falling back to direct creation: ' . $e->getMessage());
        }

        $otp = (string) random_int(100000, 999999);
        PasswordOtp::where('email', $email)->delete();
        PasswordOtp::create([
            'email' => $email,
            'otp' => Hash::make($otp),
            'expires_at' => Carbon::now()->addMinutes($minutes),
        ]);

        return $otp;
    }

    /**
     * Safely find a valid OTP, compatible with both updated and legacy PasswordOtp models.
     */
    protected function findValidOtp(string $email, $otp, bool $allowVerified = false)
    {
        try {
            if (method_exists(PasswordOtp::class, 'findValid')) {
                return PasswordOtp::findValid($email, $otp, $allowVerified);
            }
        } catch (\Throwable $e) {
            Log::warning('PasswordOtp::findValid failed in LoginController, falling back to direct query: ' . $e->getMessage());
        }

        $otp = trim((string) $otp);
        $records = PasswordOtp::where('email', $email)
            ->where('expires_at', '>', Carbon::now())
            ->latest('id')
            ->get();

        foreach ($records as $record) {
            if ($record->otp === 'VERIFIED') {
                if ($allowVerified) {
                    return $record;
                }
                continue;
            }
            if ($otp !== '' && $record->otp === $otp) {
                return $record;
            }
            if ($otp !== '' && (str_starts_with($record->otp, '$2y$') || str_starts_with($record->otp, '$2a$')) && Hash::check($otp, $record->otp)) {
                return $record;
            }
        }

        return null;
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

        // Only send a code to real admin accounts, but respond the same way either
        // way so the form cannot be used to discover admin emails.
        if (User::where('email', $email)->exists()) {
            $otp = $this->issueOtp($email);

            try {
                Mail::to($email)->send(new SendOtpMail($otp));
            } catch (\Exception $e) {
                Log::warning('Admin password reset OTP email failed: ' . $e->getMessage());
            }
        }

        session(['reset_email' => $email, 'reset_step' => 'verify']);

        return redirect()->route('forgot-password')->with('success', 'If that email belongs to an admin account, a verification code has been sent to it.');
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

        $otpRecord = $this->findValidOtp($email, $request->otp);

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
            'password' => ['required', 'string', \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers()],
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
        $otpRecord = PasswordOtp::findValid($email, $otp);

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
