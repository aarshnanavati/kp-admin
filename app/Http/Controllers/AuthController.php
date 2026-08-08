<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PasswordOtp;
use App\Mail\SendOtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Handle admin login request.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please fill in all required fields properly.',
            ], 422);
        }

        $credentials = [
            'email' => strtolower(trim($request->email)),
            'password' => $request->password,
        ];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();
            return response()->json([
                'success' => true,
                'admin' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid email or password.',
        ]);
    }

    /**
     * Handle admin registration request.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        if ($request->password !== $request->confirm_password) {
            return response()->json([
                'success' => false,
                'message' => 'Passwords do not match.',
            ]);
        }

        $email = strtolower(trim($request->email));
        if (User::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'An admin with this email already exists.',
            ]);
        }

        User::create([
            'name' => trim($request->name),
            'email' => $email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Redirecting to login...',
        ]);
    }

    /**
     * Handle logout request.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Handle request to send password reset OTP.
     */
    public function forgetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid email address.',
            ]);
        }

        $email = strtolower(trim($request->email));
        if (!User::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No admin account found with that email address.',
            ]);
        }

        // Generate 6-digit OTP
        $otp = (string)rand(100000, 999999);

        // Delete old OTPs for this email
        PasswordOtp::where('email', $email)->delete();

        // Create new OTP valid for 10 minutes
        PasswordOtp::create([
            'email' => $email,
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        // Log OTP locally for easy testing without SMTP setup
        Log::info("Password Reset OTP for {$email}: {$otp}");

        // Send actual email via Laravel Mail
        try {
            Mail::to($email)->send(new SendOtpMail($otp));
        } catch (\Exception $e) {
            Log::warning("SMTP email sending failed. Fallback: Check storage/logs/laravel.log. Error: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'A verification OTP has been sent to your email. (Also logged to laravel.log for local testing)',
        ]);
    }

    /**
     * Verify verification OTP code.
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter the 6-digit OTP sent to your email.',
            ]);
        }

        $email = strtolower(trim($request->email));
        $otpRecord = PasswordOtp::where('email', $email)
            ->where('otp', trim($request->otp))
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP. Please request a new one.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
        ]);
    }

    /**
     * Reset password using OTP code.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(' ', $validator->errors()->all()),
            ]);
        }

        if ($request->password !== $request->confirm_password) {
            return response()->json([
                'success' => false,
                'message' => 'Passwords do not match.',
            ]);
        }

        $email = strtolower(trim($request->email));
        
        // Re-verify OTP to prevent bypasses
        $otpRecord = PasswordOtp::where('email', $email)
            ->where('otp', trim($request->otp))
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'OTP verification failed. Please try requesting a new OTP.',
            ]);
        }

        // Update User Password
        $user = User::where('email', $email)->first();
        if ($user) {
            $user->update([
                'password' => Hash::make($request->password),
            ]);
        }

        // Delete the verified OTP
        $otpRecord->delete();

        return response()->json([
            'success' => true,
            'message' => 'Your password has been reset successfully. Redirecting to login...',
        ]);
    }
}
