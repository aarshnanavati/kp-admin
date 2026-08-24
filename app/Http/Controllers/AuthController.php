<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Customer;
use App\Models\PasswordOtp;
use App\Mail\SendOtpMail;
use App\Mail\KitchenAlertMail;
use Illuminate\Support\Str;
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
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }
            $user = Auth::user();
            $token = Str::random(60);
            $user->update(['api_token' => $token]);

            try {
                Mail::to('admin@kpkitchen.com')->send(new KitchenAlertMail("User Login Notification", "{$user->name} has logged in into KP's Kitchen."));
            } catch (\Exception $e) {
                Log::warning("Admin login alert email failed: " . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'token' => $token,
                'user_type' => $user->user_type,
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

        $user = User::create([
            'name' => trim($request->name),
            'email' => $email,
            'password' => Hash::make($request->password),
            'user_type' => 'admin',
        ]);

        try {
            Mail::to($user->email)->send(new KitchenAlertMail("Welcome to KP's Kitchen Admin Panel!", "Thank you {$user->name} for registering in KP's Kitchen admin team!"));
            Mail::to('admin@kpkitchen.com')->send(new KitchenAlertMail("New User Registration", "new user {$user->name} have registerd"));
        } catch (\Exception $e) {
            Log::warning("Admin registration email triggers failed: " . $e->getMessage());
        }

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
        $user = Auth::user();
        if ($user) {
            $user->update(['api_token' => null]);
        }
        Auth::logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

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

    /**
     * Handle customer registration.
     */
    public function customerRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'password' => 'required|string|min:6',
            'pincode' => 'required|string|max:10',
            'address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $email = strtolower(trim($request->email));
        if (Customer::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'A customer with this email already exists.',
            ], 422);
        }

        $token = Str::random(60);

        $customer = Customer::create([
            'name' => trim($request->name),
            'email' => $email,
            'phone' => trim($request->phone),
            'password' => Hash::make($request->password),
            'pincode' => trim($request->pincode),
            'address' => trim($request->address),
            'api_token' => $token,
            'user_type' => 'customer',
        ]);

        try {
            Mail::to($customer->email)->send(new KitchenAlertMail("Welcome to KP's Kitchen!", "Thank you {$customer->name} for registering in KP's Kitchen. We are excited to serve you delicious meals!"));
            Mail::to('admin@kpkitchen.com')->send(new KitchenAlertMail("New User Registration", "new user {$customer->name} have registerd"));
        } catch (\Exception $e) {
            Log::warning("Customer registration email triggers failed: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Registration successful.',
            'token' => $token,
            'user_type' => $customer->user_type,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'pincode' => $customer->pincode,
                'address' => $customer->address,
            ]
        ], 201);
    }

    /**
     * Handle customer login.
     */
    public function customerLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide email and password.',
            ], 422);
        }

        $email = strtolower(trim($request->email));
        $customer = Customer::where('email', $email)->first();

        if (!$customer || !Hash::check($request->password, $customer->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = Str::random(60);
        $customer->update(['api_token' => $token]);

        // Cart Shifting Logic: Move items from guest_carts to carts table
        $tempUserId = $request->input('temp_user_id');
        if ($tempUserId) {
            $tempCartItems = \App\Models\GuestCart::where('temp_user_id', $tempUserId)->get();
            foreach ($tempCartItems as $item) {
                // Check if customer already has this exact item or tiffin in their permanent cart
                $existingItem = \App\Models\Cart::where('customer_id', $customer->id)
                    ->where('tiffin_id', $item->tiffin_id)
                    ->where('item_id', $item->item_id)
                    ->first();

                if ($existingItem) {
                    $existingItem->increment('quantity', $item->quantity);
                } else {
                    \App\Models\Cart::create([
                        'customer_id' => $customer->id,
                        'tiffin_id' => $item->tiffin_id,
                        'item_id' => $item->item_id,
                        'quantity' => $item->quantity
                    ]);
                }
                $item->delete();
            }
        }

        // First Login Notification Alert
        if ($customer->login_count === 0) {
            \App\Models\Notification::create([
                'title' => 'First Login Alert',
                'message' => "Customer {$customer->name} has logged in for the first time!",
                'read_status' => false
            ]);
        }
        $customer->increment('login_count');

        try {
            Mail::to('admin@kpkitchen.com')->send(new KitchenAlertMail("User Login Notification", "{$customer->name} has logged in into KP's Kitchen."));
        } catch (\Exception $e) {
            Log::warning("Customer login alert email failed: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'user_type' => $customer->user_type,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'pincode' => $customer->pincode,
                'address' => $customer->address,
            ]
        ]);
    }

    /**
     * Handle customer logout.
     */
    public function customerLogout(Request $request)
    {
        $customer = $request->attributes->get('customer');
        if ($customer) {
            $customer->update(['api_token' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.'
        ]);
    }

    /**
     * Get authenticated customer profile.
     */
    public function customerProfile(Request $request)
    {
        $customer = $request->attributes->get('customer');
        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'pincode' => $customer->pincode,
                'address' => $customer->address,
                'user_type' => $customer->user_type,
                'total_orders' => $customer->orders()->count(),
                'total_spent' => (float)$customer->orders()->sum('amount'),
                'recent_orders' => $customer->orders()->latest()->take(5)->get(),
            ]
        ]);
    }

    /**
     * Get orders for the authenticated customer.
     */
    public function customerOrders(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $orders = \App\Models\Order::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json([
            'success' => true,
            'orders' => $orders
        ]);
    }

    /**
     * Place a new order for the customer.
     */
    public function placeCustomerOrder(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $validator = Validator::make($request->all(), [
            'tiffin_id' => 'required|exists:tiffins,id',
            'add_ons' => 'nullable|array',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $tiffin = \App\Models\Tiffin::findOrFail($request->tiffin_id);
        
        $amount = (float)$tiffin->price;
        $addons = $request->input('add_ons', []);
        if (!empty($addons)) {
            foreach ($addons as $addonId) {
                $item = \App\Models\Item::find($addonId);
                if ($item) {
                    $amount += (float)$item->price;
                }
            }
        }

        $orderId = 'ORD' . strtoupper(Str::random(8));

        $addonsData = [];
        foreach ($addons as $addonId) {
            $item = \App\Models\Item::find($addonId);
            if ($item) {
                $addonsData[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => $item->price,
                    'qty' => 1
                ];
            }
        }

        $order = \App\Models\Order::create([
            'id' => $orderId,
            'customer_id' => $customer->id,
            'customer' => $customer->name,
            'tiffin_id' => $tiffin->id,
            'tiffin' => $tiffin->name,
            'area' => $customer->pincode,
            'amount' => $amount,
            'status' => 'Pending',
            'date' => Carbon::now()->toDateString(),
            'add_ons' => json_encode($addonsData),
            'note' => $request->input('note'),
        ]);

        \App\Models\Notification::create([
            'title' => 'New Order Placed',
            'message' => "Customer {$customer->name} placed order {$orderId} for plan {$tiffin->name}.",
            'read_status' => false
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully.',
            'order' => $order
        ]);
    }

    /**
     * Get invoices for the authenticated customer.
     */
    public function customerInvoices(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $invoices = \App\Models\Invoice::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json([
            'success' => true,
            'invoices' => $invoices
        ]);
    }

    /**
     * Get customer notifications.
     */
    public function customerNotifications(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $notifications = \App\Models\Notification::where('message', 'like', "%{$customer->name}%")
            ->orWhere('title', 'like', '%System%')
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json([
            'success' => true,
            'notifications' => $notifications
        ]);
    }

    /**
     * Apply coupon code.
     */
    public function applyCoupon(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid coupon code.'
            ], 422);
        }

        $coupon = \App\Models\Coupon::where('code', strtoupper(trim($request->code)))
            ->where('status', 'Active')
            ->where('expiry', '>=', Carbon::now()->toDateString())
            ->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired coupon code.'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Coupon code applied successfully.',
            'coupon' => [
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => (float)$coupon->value
            ]
        ]);
    }

    /**
     * Handle driver registration.
     */
    public function driverRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6',
            'address' => 'nullable|string',
            'license_no' => 'nullable|string|max:100',
            'license_expiry' => 'nullable|date',
            'vehicle_reg_no' => 'nullable|string|max:50',
            'assigned_zip' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $email = strtolower(trim($request->email));
        if (\App\Models\Driver::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'A driver with this email already exists.',
            ], 422);
        }

        $token = Str::random(60);

        $driver = \App\Models\Driver::create([
            'name' => trim($request->name),
            'phone' => trim($request->phone),
            'email' => $email,
            'password' => Hash::make($request->password),
            'address' => $request->address ? trim($request->address) : null,
            'license_no' => $request->license_no ? trim($request->license_no) : null,
            'license_expiry' => $request->license_expiry ? trim($request->license_expiry) : null,
            'vehicle_reg_no' => $request->vehicle_reg_no ? trim($request->vehicle_reg_no) : null,
            'assigned_zip' => $request->assigned_zip ? trim($request->assigned_zip) : null,
            'area' => $request->assigned_zip ? trim($request->assigned_zip) : null,
            'api_token' => $token,
            'status' => 'Active',
            'user_type' => 'driver',
        ]);

        try {
            Mail::to($driver->email)->send(new KitchenAlertMail("Welcome to KP's Kitchen Team!", "Thank you {$driver->name} for registering in KP's Kitchen. We are excited to have you as part of our driver network!"));
            Mail::to('admin@kpkitchen.com')->send(new KitchenAlertMail("New User Registration", "new user {$driver->name} have registerd"));
        } catch (\Exception $e) {
            Log::warning("Driver registration email triggers failed: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Driver registration successful.',
            'token' => $token,
            'user_type' => $driver->user_type,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'assigned_zip' => $driver->assigned_zip,
            ]
        ], 201);
    }

    /**
     * Handle driver login.
     */
    public function driverLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide email and password.',
            ], 422);
        }

        $email = strtolower(trim($request->email));
        $driver = \App\Models\Driver::where('email', $email)->first();

        if (!$driver || !Hash::check($request->password, $driver->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = Str::random(60);
        $driver->update(['api_token' => $token]);

        try {
            Mail::to('admin@kpkitchen.com')->send(new KitchenAlertMail("User Login Notification", "{$driver->name} has logged in into KP's Kitchen."));
        } catch (\Exception $e) {
            Log::warning("Driver login alert email failed: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Driver login successful.',
            'token' => $token,
            'user_type' => $driver->user_type,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'assigned_zip' => $driver->assigned_zip,
            ]
        ]);
    }

    /**
     * Handle driver logout.
     */
    public function driverLogout(Request $request)
    {
        $driver = $request->attributes->get('driver');
        if ($driver) {
            $driver->update(['api_token' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Driver logged out successfully.'
        ]);
    }

    /**
     * Get authenticated driver profile.
     */
    public function driverProfile(Request $request)
    {
        $driver = $request->attributes->get('driver');
        return response()->json([
            'success' => true,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'address' => $driver->address,
                'license_no' => $driver->license_no,
                'license_expiry' => $driver->license_expiry,
                'license_copy_front' => $driver->license_copy_front ? asset($driver->license_copy_front) : null,
                'license_copy_back' => $driver->license_copy_back ? asset($driver->license_copy_back) : null,
                'vehicle_reg_no' => $driver->vehicle_reg_no,
                'assigned_zip' => $driver->assigned_zip,
                'status' => $driver->status,
                'user_type' => $driver->user_type,
                'total_assigned_orders' => $driver->orders()->count(),
                'active_shipments' => $driver->orders()->whereIn('status', ['Cooking', 'Dispatched'])->count(),
                'recent_deliveries' => $driver->orders()->latest()->take(5)->get(),
            ]
        ]);
    }

    /**
     * Customer Forgot Password
     */
    public function customerForgetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid email address.',
            ], 422);
        }

        $email = strtolower(trim($request->email));
        if (!Customer::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No customer account found with that email address.',
            ], 422);
        }

        $otp = (string)rand(100000, 999999);
        PasswordOtp::where('email', $email)->delete();
        PasswordOtp::create([
            'email' => $email,
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        Log::info("Customer Password Reset OTP for {$email}: {$otp}");

        try {
            Mail::to($email)->send(new SendOtpMail($otp));
        } catch (\Exception $e) {
            Log::warning("SMTP email sending failed. Fallback logged in laravel.log.");
        }

        return response()->json([
            'success' => true,
            'message' => 'A verification OTP has been sent to your email.',
        ]);
    }

    /**
     * Customer Verify OTP
     */
    public function customerVerifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter the 6-digit OTP sent to your email.',
            ], 422);
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
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
        ]);
    }

    /**
     * Customer Reset Password
     */
    public function customerResetPassword(Request $request)
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
            ], 422);
        }

        if ($request->password !== $request->confirm_password) {
            return response()->json([
                'success' => false,
                'message' => 'Passwords do not match.',
            ], 422);
        }

        $email = strtolower(trim($request->email));
        $otpRecord = PasswordOtp::where('email', $email)
            ->where('otp', trim($request->otp))
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'OTP verification failed. Please request a new OTP.',
            ], 422);
        }

        $customer = Customer::where('email', $email)->first();
        if ($customer) {
            $customer->update([
                'password' => Hash::make($request->password),
            ]);
        }

        $otpRecord->delete();

        return response()->json([
            'success' => true,
            'message' => 'Your password has been reset successfully.',
        ]);
    }

    /**
     * Driver Forgot Password
     */
    public function driverForgetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid email address.',
            ], 422);
        }

        $email = strtolower(trim($request->email));
        if (!\App\Models\Driver::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No driver account found with that email address.',
            ], 422);
        }

        $otp = (string)rand(100000, 999999);
        PasswordOtp::where('email', $email)->delete();
        PasswordOtp::create([
            'email' => $email,
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        Log::info("Driver Password Reset OTP for {$email}: {$otp}");

        try {
            Mail::to($email)->send(new SendOtpMail($otp));
        } catch (\Exception $e) {
            Log::warning("SMTP email sending failed. Fallback logged in laravel.log.");
        }

        return response()->json([
            'success' => true,
            'message' => 'A verification OTP has been sent to your email.',
        ]);
    }

    /**
     * Driver Verify OTP
     */
    public function driverVerifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter the 6-digit OTP sent to your email.',
            ], 422);
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
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
        ]);
    }

    /**
     * Driver Reset Password
     */
    public function driverResetPassword(Request $request)
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
            ], 422);
        }

        if ($request->password !== $request->confirm_password) {
            return response()->json([
                'success' => false,
                'message' => 'Passwords do not match.',
            ], 422);
        }

        $email = strtolower(trim($request->email));
        $otpRecord = PasswordOtp::where('email', $email)
            ->where('otp', trim($request->otp))
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'OTP verification failed. Please request a new OTP.',
            ], 422);
        }

        $driver = \App\Models\Driver::where('email', $email)->first();
        if ($driver) {
            $driver->update([
                'password' => Hash::make($request->password),
            ]);
        }

        $otpRecord->delete();

        return response()->json([
            'success' => true,
            'message' => 'Your password has been reset successfully.',
        ]);
    }

    /**
     * Get profile for the authenticated administrator.
     */
    public function adminProfile(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'admin' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'user_type' => $user->user_type,
                'created_at' => $user->created_at->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get cart items.
     */
    public function getCart(Request $request)
    {
        // Clean up guest carts inactive for more than 5 days
        \App\Models\GuestCart::where('updated_at', '<', now()->subDays(5))->delete();

        $customerId = null;
        $token = $request->bearerToken();
        if ($token) {
            $customer = Customer::where('api_token', $token)->first();
            if ($customer) {
                $customerId = $customer->id;
            }
        }

        $tempUserId = $request->input('temp_user_id');

        if (!$customerId && !$tempUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide a Bearer Token or a temp_user_id.'
            ], 400);
        }

        if ($customerId) {
            $query = \App\Models\Cart::with(['tiffin', 'item'])->where('customer_id', $customerId);
        } else {
            $query = \App\Models\GuestCart::with(['tiffin', 'item'])->where('temp_user_id', $tempUserId);
        }

        $cartItems = $query->get()->map(function ($cart) {
            return [
                'id' => $cart->id,
                'quantity' => $cart->quantity,
                'tiffin' => $cart->tiffin ? [
                    'id' => $cart->tiffin->id,
                    'name' => $cart->tiffin->name,
                    'price' => $cart->tiffin->price,
                    'image' => $cart->tiffin->image,
                ] : null,
                'item' => $cart->item ? [
                    'id' => $cart->item->id,
                    'name' => $cart->item->name,
                    'price' => $cart->item->price,
                    'image' => $cart->item->image,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'cart' => $cartItems
        ]);
    }

    /**
     * Add an item to the cart.
     */
    public function addToCart(Request $request)
    {
        // Clean up guest carts inactive for more than 5 days
        \App\Models\GuestCart::where('updated_at', '<', now()->subDays(5))->delete();

        $customerId = null;
        $token = $request->bearerToken();
        if ($token) {
            $customer = Customer::where('api_token', $token)->first();
            if ($customer) {
                $customerId = $customer->id;
            }
        }

        $tempUserId = $request->input('temp_user_id');

        if (!$customerId && !$tempUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication Bearer Token or temp_user_id is required.'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'tiffin_id' => 'nullable|exists:tiffins,id',
            'item_id' => 'nullable|exists:items,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $tiffinId = $request->tiffin_id;
        $itemId = $request->item_id;
        $quantity = $request->input('quantity', 1);

        if (!$tiffinId && !$itemId) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide either a tiffin_id or an item_id to add to the cart.'
            ], 400);
        }

        if ($customerId) {
            $cartItem = \App\Models\Cart::where('customer_id', $customerId)
                ->where('tiffin_id', $tiffinId)
                ->where('item_id', $itemId)
                ->first();

            if ($cartItem) {
                $cartItem->increment('quantity', $quantity);
            } else {
                $cartItem = \App\Models\Cart::create([
                    'customer_id' => $customerId,
                    'tiffin_id' => $tiffinId,
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                ]);
            }
        } else {
            $cartItem = \App\Models\GuestCart::where('temp_user_id', $tempUserId)
                ->where('tiffin_id', $tiffinId)
                ->where('item_id', $itemId)
                ->first();

            if ($cartItem) {
                $cartItem->increment('quantity', $quantity);
            } else {
                $cartItem = \App\Models\GuestCart::create([
                    'temp_user_id' => $tempUserId,
                    'tiffin_id' => $tiffinId,
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Item added to cart successfully.',
            'cart_item' => $cartItem
        ]);
    }

    /**
     * Remove or update item in the cart.
     */
    public function removeFromCart(Request $request)
    {
        $customerId = null;
        $token = $request->bearerToken();
        if ($token) {
            $customer = Customer::where('api_token', $token)->first();
            if ($customer) {
                $customerId = $customer->id;
            }
        }

        $tempUserId = $request->input('temp_user_id');

        if (!$customerId && !$tempUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication Bearer Token or temp_user_id is required.'
            ], 400);
        }

        $table = $customerId ? 'carts' : 'guest_carts';

        $validator = Validator::make($request->all(), [
            'cart_item_id' => 'required|integer|exists:' . $table . ',id',
            'quantity' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        if ($customerId) {
            $cartItem = \App\Models\Cart::where('id', $request->cart_item_id)
                ->where('customer_id', $customerId)
                ->first();
        } else {
            $cartItem = \App\Models\GuestCart::where('id', $request->cart_item_id)
                ->where('temp_user_id', $tempUserId)
                ->first();
        }

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found or does not belong to this user.'
            ], 404);
        }

        $quantity = $request->input('quantity', 0);

        if ($quantity == 0) {
            $cartItem->delete();
            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart successfully.'
            ]);
        } else {
            $cartItem->update(['quantity' => $quantity]);
            return response()->json([
                'success' => true,
                'message' => 'Cart item quantity updated successfully.',
                'cart_item' => $cartItem
            ]);
        }
    }

    /**
     * Get orders assigned to the authenticated driver.
     */
    public function getDriverAssignedOrders(Request $request)
    {
        $driver = $request->attributes->get('driver');
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $orders = \App\Models\Order::where('driver_id', $driver->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'orders' => $orders
        ]);
    }

    /**
     * Update status and upload proof of delivery (POD) for an assigned order.
     */
    public function updateDriverOrderStatus(Request $request, $id)
    {
        $driver = $request->attributes->get('driver');
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $order = \App\Models\Order::where('id', $id)
            ->where('driver_id', $driver->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or not assigned to this driver.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:Pending,Out for Delivery,Delivered,Failed',
            'proof_photo' => 'nullable|image|max:5120',
            'proof_signature' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $order->status = $request->status;

        // Handle proof of delivery photo upload
        if ($request->hasFile('proof_photo')) {
            $file = $request->file('proof_photo');
            $uploadedHash = md5_file($file->getRealPath());

            // Check against existing orders' pod photos
            $existingOrders = \App\Models\Order::whereNotNull('proof_of_delivery_photo')->get();
            foreach ($existingOrders as $existingOrder) {
                if ($existingOrder->id !== $order->id) {
                    $existingFilePath = public_path($existingOrder->proof_of_delivery_photo);
                    if (file_exists($existingFilePath)) {
                        if (md5_file($existingFilePath) === $uploadedHash) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Please take a new photo. You cannot upload the same image for multiple deliveries.'
                            ], 422);
                        }
                    }
                }
            }

            $fileName = 'pod_photo_' . $order->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/pod'), $fileName);
            $order->proof_of_delivery_photo = 'uploads/pod/' . $fileName;
        }

        // Handle proof of delivery signature upload
        if ($request->hasFile('proof_signature')) {
            $file = $request->file('proof_signature');
            $fileName = 'pod_sig_' . $order->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/pod'), $fileName);
            $order->proof_of_delivery_signature = 'uploads/pod/' . $fileName;
        }

        $order->save();

        \App\Models\Notification::create([
            'title' => 'Order Status Updated',
            'message' => "Driver {$driver->name} updated order {$order->id} status to {$order->status}.",
            'read_status' => false
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully.',
            'order' => $order
        ]);
    }
}
