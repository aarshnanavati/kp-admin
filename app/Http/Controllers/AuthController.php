<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\Order;
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
use App\Services\FcmService;
use App\Helpers\AddressHelper;
use App\Helpers\ImageUploadHelper;
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
        if ($request->has('first_name') && $request->has('last_name')) {
            $request->merge([
                'name' => trim($request->first_name . ' ' . $request->last_name)
            ]);
        } elseif ($request->has('name')) {
            $nameParts = explode(' ', $request->name, 2);
            $request->merge([
                'first_name' => $nameParts[0] ?? '',
                'last_name' => $nameParts[1] ?? ''
            ]);
        }

        // Normalize field aliases from mobile apps and web forms
        if ($request->has('delivery_address') && !$request->has('address')) {
            $request->merge(['address' => $request->delivery_address]);
        }
        if ($request->has('postcode') && !$request->has('pincode')) {
            $request->merge(['pincode' => $request->postcode]);
        }
        if ($request->has('postal_code') && !$request->has('pincode')) {
            $request->merge(['pincode' => $request->postal_code]);
        }
        if ($request->has('zip') && !$request->has('pincode')) {
            $request->merge(['pincode' => $request->zip]);
        }
        if ($request->has('suburbs') && !$request->has('suburb')) {
            $request->merge(['suburb' => $request->suburbs]);
        }
        if ($request->has('suburb') && !$request->has('city')) {
            $request->merge(['city' => $request->suburb]);
        }
        if ($request->has('confirm_password') && !$request->has('password_confirmation')) {
            $request->merge(['password_confirmation' => $request->confirm_password]);
        }
        if ($request->has('password_confirmation') && !$request->has('confirm_password')) {
            $request->merge(['confirm_password' => $request->password_confirmation]);
        }
        if (!$request->filled('phone')) {
            $request->merge(['phone' => '0400' . rand(100000, 999999)]);
        }

        // Extract structured address fields (street_address, city/suburb, pincode/postcode, address)
        $addressInfo = AddressHelper::extractAndFormat($request);
        $streetAddress = $addressInfo['street_address'];
        $city = $addressInfo['city'];
        $pincode = $addressInfo['pincode'];
        $formattedAddress = $addressInfo['address'];

        if ($formattedAddress && !$request->has('address')) {
            $request->merge(['address' => $formattedAddress]);
        }
        if ($pincode && !$request->has('pincode')) {
            $request->merge(['pincode' => $pincode]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required_without:first_name|string|max:255',
            'first_name' => 'required_without:name|string|max:255',
            'last_name' => 'required_without:name|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'password' => 'required|string|min:6|confirmed',
            'confirm_password' => 'required_without:password_confirmation|string|min:6',
            'password_confirmation' => 'required_without:confirm_password|string|min:6',
            'pincode' => 'nullable|string|max:20',
            'postcode' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'street_address' => 'nullable|string',
            'city' => 'nullable|string',
            'suburb' => 'nullable|string',
            'suburbs' => 'nullable|string',
            'town' => 'nullable|string',
        ], [
            'password.confirmed' => 'The password and confirm password do not match.',
            'confirm_password.required_without' => 'The confirm password field is required.',
            'password_confirmation.required_without' => 'The confirm password field is required.',
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
            'street_address' => $streetAddress,
            'city' => $city,
            'pincode' => $pincode ?: trim($request->pincode),
            'address' => $formattedAddress ?: trim($request->address),
            'api_token' => $token,
            'user_type' => 'customer',
            'fcm_token' => $request->input('fcm_token'),
        ]);

        $defaultAddr = $customer->addresses()->create([
            'type' => 'Home',
            'street_address' => $streetAddress,
            'city' => $city,
            'address_line' => $formattedAddress ?: trim($request->address),
            'pincode' => $pincode ?: trim($request->pincode),
            'is_default' => true,
        ]);

        try {
            Mail::to($customer->email)->send(new KitchenAlertMail("Welcome to KP's Kitchen!", "Thank you {$customer->name} for registering in KP's Kitchen. We are excited to serve you delicious meals!"));
            Mail::to('admin@kpkitchen.com')->send(new KitchenAlertMail("New User Registration", "new user {$customer->name} have registerd"));
        } catch (\Exception $e) {
            Log::warning("Customer registration email triggers failed: " . $e->getMessage());
        }

        $formattedAddrRecord = AddressHelper::formatAddressRecord($defaultAddr);
        $customerData = array_merge([
            'id' => $customer->id,
            'name' => $customer->name,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'profile_image' => null,
            'status' => 'Active',
            'user_type' => $customer->user_type,
            'addresses' => [$formattedAddrRecord],
        ], AddressHelper::formatResponsePayload($customer));

        return response()->json([
            'success' => true,
            'message' => 'Registration successful.',
            'token' => $token,
            'user_type' => $customer->user_type,
            'customer' => $customerData,
            'data' => [
                'token' => $token,
                'customer' => $customerData,
                'user' => $customerData,
            ]
        ], 201);
    }

    /**
     * Handle customer login.
     */
    public function customerLogin(Request $request)
    {
        $loginInput = trim($request->input('email') ?: $request->input('phone') ?: $request->input('username') ?: '');
        $password = $request->input('password');

        if (empty($loginInput) || empty($password)) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide email or phone and password.',
            ], 422);
        }

        $customer = Customer::where('email', strtolower($loginInput))
            ->orWhere('phone', $loginInput)
            ->first();

        if (!$customer || !Hash::check($password, $customer->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = Str::random(60);
        $updateFields = ['api_token' => $token];
        if ($request->filled('fcm_token')) {
            $updateFields['fcm_token'] = $request->fcm_token;
        }
        $customer->update($updateFields);

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

        // First Login Notification Alert for Admin
        if ($customer->login_count === 0) {
            \App\Models\Notification::create([
                'title' => 'First Login Alert',
                'message' => "Customer {$customer->name} has logged in for the first time!",
                'user_type' => 'admin',
                'user_id' => null,
                'read_status' => false
            ]);
            FcmService::sendToAdmin(
                'First Login Alert',
                "Customer {$customer->name} has logged in for the first time!",
                ['type' => 'customer_first_login', 'customer_id' => $customer->id]
            );
        }

        // Dedicated Customer Login Notification
        \App\Models\Notification::create([
            'title' => 'Login Successful',
            'message' => "Welcome back, {$customer->name}! You have successfully logged in.",
            'user_type' => 'customer',
            'user_id' => $customer->id,
            'read_status' => false
        ]);

        FcmService::sendToCustomer(
            $customer->id,
            'Login Successful',
            "Welcome back, {$customer->name}! You have successfully logged in.",
            ['type' => 'login']
        );

        $customer->increment('login_count');

        try {
            Mail::to('admin@kpkitchen.com')->send(new KitchenAlertMail("User Login Notification", "{$customer->name} has logged in into KP's Kitchen."));
        } catch (\Exception $e) {
            Log::warning("Customer login alert email failed: " . $e->getMessage());
        }

        $formattedAddresses = $customer->addresses()->orderBy('is_default', 'desc')->get()->map(function ($addr) {
            return AddressHelper::formatAddressRecord($addr);
        });

        $customerData = array_merge([
            'id' => $customer->id,
            'name' => $customer->name,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'profile_image' => $customer->profile_image ? asset($customer->profile_image) : null,
            'user_type' => $customer->user_type,
            'status' => $customer->status ?? 'Active',
            'account_status' => $customer->status ?? 'Active',
            'addresses' => $formattedAddresses,
        ], AddressHelper::formatResponsePayload($customer));

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'user_type' => $customer->user_type,
            'customer' => $customerData,
            'data' => [
                'token' => $token,
                'customer' => $customerData,
                'user' => $customerData,
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

        $addresses = $customer->addresses()->orderBy('is_default', 'desc')->get();
        if ($addresses->isEmpty() && $customer->address) {
            $addrInfo = AddressHelper::extractAndFormat($customer);
            $created = $customer->addresses()->create([
                'type' => 'Home',
                'street_address' => $addrInfo['street_address'],
                'city' => $addrInfo['city'],
                'address_line' => $addrInfo['address'],
                'pincode' => $addrInfo['pincode'],
                'is_default' => true,
            ]);
            $addresses = collect([$created]);
        }

        $formattedAddresses = $addresses->map(fn($a) => AddressHelper::formatAddressRecord($a));

        $customerData = array_merge([
            'id' => $customer->id,
            'name' => $customer->name,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'profile_image' => $customer->profile_image ? asset($customer->profile_image) : null,
            'user_type' => $customer->user_type,
            'total_orders' => $customer->orders()->count(),
            'total_spent' => (float)$customer->orders()->sum('amount'),
            'recent_orders' => $customer->orders()->latest()->take(5)->get(),
            'addresses' => $formattedAddresses,
        ], AddressHelper::formatResponsePayload($customer));

        return response()->json([
            'success' => true,
            'customer' => $customerData
        ]);
    }

    /**
     * Get orders for the authenticated customer.
     */
    public function customerOrders(Request $request)
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $this->ensureInvoicesForCustomerOrders($customer);

        $invoices = \App\Models\Invoice::where('customer_id', $customer->id)->get();

        $orders = \App\Models\Order::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) use ($customer, $invoices) {
                $dt = Carbon::parse($order->date);
                $wStart = $dt->copy()->startOfWeek()->toDateString();
                $wEnd = $dt->copy()->endOfWeek()->toDateString();
                $wYear = $dt->year;
                $wWeekNum = $dt->weekOfYear;
                $prefix = 'INV-W' . $wYear . str_pad((string)$wWeekNum, 2, '0', STR_PAD_LEFT) . '-' . $customer->id;

                $invoice = $invoices->first(function ($inv) use ($prefix, $wStart, $wEnd, $order) {
                    if (str_starts_with($inv->id, $prefix)) return true;
                    if ($inv->due_date && $inv->due_date >= $wStart && $inv->due_date <= $wEnd) return true;
                    if ($inv->order_id && str_contains($inv->order_id, $order->id)) return true;
                    return false;
                });

                $isPaid = $invoice ? ($invoice->status === 'Paid') : false;
                $weeklyBillId = $invoice ? $invoice->id : ($prefix . '-001');
                $weeklyBillStatus = $invoice ? $invoice->status : 'Pending';
                $weeklyBillAmount = $invoice ? (float)$invoice->amount : (float)$order->amount;
                $weekRange = Carbon::parse($wStart)->format('d M Y') . ' - ' . Carbon::parse($wEnd)->format('d M Y');

                // If weekly invoice is paid and order status is 'Payment Pending', update order status to 'Pending'
                $orderStatus = $order->status;
                if ($isPaid && $orderStatus === 'Payment Pending') {
                    $orderStatus = 'Pending';
                    $order->status = 'Pending';
                    $order->save();
                }

                $paymentStatus = $isPaid 
                    ? 'Paid' 
                    : (in_array($orderStatus, ['Delivered', 'Completed']) ? 'Paid' : 'Weekly Billed');

                $addrDetails = AddressHelper::extractAndFormat($order, $customer);
                $formattedOrderAddress = $addrDetails['address'];

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number ?: ('ORD-' . $order->id),
                    'customer_id' => $order->customer_id,
                    'customer' => $order->customer,
                    'customer_address' => $formattedOrderAddress,
                    'delivery_address' => $formattedOrderAddress,
                    'formatted_address' => $formattedOrderAddress,
                    'street_address' => $addrDetails['street_address'],
                    'city' => $addrDetails['city'],
                    'suburb' => $addrDetails['city'],
                    'pincode' => $addrDetails['pincode'],
                    'postal_code' => $addrDetails['pincode'],
                    'address_details' => $addrDetails['address_details'],
                    'tiffin_id' => $order->tiffin_id,
                    'tiffin_plan_name' => $order->tiffin,
                    'tiffin' => $order->tiffin,
                    'quantity' => $order->quantity,
                    'amount' => $order->amount,
                    'total_amount' => (string)$order->amount,
                    'delivery_date' => $order->date,
                    'date' => $order->date,
                    'status' => $orderStatus,
                    'driver' => $order->driver,
                    'proof_of_delivery_photo' => $order->proof_of_delivery_photo ? asset('public/' . ltrim($order->proof_of_delivery_photo, '/')) : null,
                    'proof_of_delivery_signature' => $order->proof_of_delivery_signature ? asset('public/' . ltrim($order->proof_of_delivery_signature, '/')) : null,
                    'created_at' => $order->created_at,
                    'is_paid' => $isPaid,
                    'payment_status' => $paymentStatus,
                    'weekly_bill_id' => $weeklyBillId,
                    'bill_id' => $weeklyBillId,
                    'invoice_id' => $weeklyBillId,
                    'weekly_bill_status' => $weeklyBillStatus,
                    'weekly_bill_amount' => $weeklyBillAmount,
                    'week_range' => $weekRange,
                    'is_weekly_billed' => true,
                    'is_weekly_billing' => true,
                    'billing_cycle' => 'Weekly Consolidated',
                ];
            });

        return response()->json([
            'success' => true,
            'orders' => $orders,
            'data' => [
                'data' => $orders,
                'orders' => $orders,
            ]
        ]);
    }

    /**
     * Get current week's running payment and order metrics.
     */
    public function getCurrentWeeklyPayment(Request $request)
    {
        $customer = $request->attributes->get('customer') ?: \App\Models\Customer::find($request->input('customer_id'));
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // Ensure physical weekly invoice records exist
        $this->ensureInvoicesForCustomerOrders($customer);

        $now = Carbon::now();
        $startOfWeek = $now->copy()->startOfWeek()->toDateString();
        $endOfWeek = $now->copy()->endOfWeek()->toDateString();
        $dueSunday = $now->copy()->endOfWeek()->format('d M Y');

        $currentWeekOrders = \App\Models\Order::where('customer_id', $customer->id)
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->get();

        $totalAmount = (float) $currentWeekOrders->sum('amount');
        $totalOrders = $currentWeekOrders->count();

        // Calculate overdue invoices (due date is strictly in past)
        $overdueInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['Pending', 'Unpaid'])
            ->whereDate('due_date', '<', $now->toDateString())
            ->get();
        $overdueBalance = (float) $overdueInvoices->sum('amount');
        $hasOverdue = $overdueBalance > 0;
        $overdueBillId = $hasOverdue ? 'overdue' : null;
        $primaryOverdueId = $overdueInvoices->isNotEmpty() ? $overdueInvoices->first()->id : null;

        // Calculate all outstanding unpaid invoices
        $allUnpaid = \App\Models\Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['Pending', 'Unpaid'])
            ->get();
        $outstandingBalance = (float) $allUnpaid->sum('amount');

        // Current week invoice
        $prefix = 'INV-W' . $now->year . str_pad((string) $now->weekOfYear, 2, '0', STR_PAD_LEFT) . '-' . $customer->id;
        $currentWeekInv = \App\Models\Invoice::where('customer_id', $customer->id)
            ->where(function ($q) use ($prefix, $startOfWeek, $endOfWeek) {
                $q->where('id', 'like', $prefix . '%')
                  ->orWhereBetween('due_date', [$startOfWeek, $endOfWeek]);
            })
            ->first();

        $currentWeekBillId = $currentWeekInv ? $currentWeekInv->id : ($prefix . '-001');

        $currentWeekUnpaid = \App\Models\Invoice::where('customer_id', $customer->id)
            ->where(function ($q) use ($prefix, $startOfWeek, $endOfWeek) {
                $q->where('id', 'like', $prefix . '%')
                  ->orWhereBetween('due_date', [$startOfWeek, $endOfWeek]);
            })
            ->whereIn('status', ['Pending', 'Unpaid'])
            ->sum('amount');
        $currentWeekBalance = (float) ($currentWeekUnpaid > 0 ? $currentWeekUnpaid : ($totalAmount > 0 && (!$currentWeekInv || $currentWeekInv->status !== 'Paid') ? $totalAmount : 0.00));

        $data = [
            'id' => $currentWeekBillId,
            'bill_id' => $currentWeekBillId,
            'weekly_bill_id' => $currentWeekBillId,
            'current_bill_id' => $currentWeekBillId,
            'current_week_id' => $currentWeekBillId,
            'total_orders' => $totalOrders,
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'current_week_amount' => round($totalAmount, 2),
            'weekly_balance' => round($currentWeekBalance, 2),
            'due_by' => $dueSunday,
            'status' => $hasOverdue ? 'Overdue Invoices Pending' : ($totalAmount > 0 ? 'Pending Sunday Invoice' : 'Paid'),
            'week_range' => Carbon::parse($startOfWeek)->format('d M Y') . ' - ' . Carbon::parse($endOfWeek)->format('d M Y'),
            'start_date' => $startOfWeek,
            'end_date' => $endOfWeek,
            'orders' => $currentWeekOrders,
            'has_overdue' => $hasOverdue,
            'overdue_balance' => round($overdueBalance, 2),
            'overdue_amount' => round($overdueBalance, 2),
            'overdue_bill_id' => $overdueBillId,
            'primary_overdue_bill_id' => $primaryOverdueId,
            'overdue_invoices_count' => $overdueInvoices->count(),
            'outstanding_balance' => round($outstandingBalance, 2),
            'total_amount_due' => round($outstandingBalance > 0 ? $outstandingBalance : $totalAmount, 2),
            'all_due_amount' => round($outstandingBalance > 0 ? $outstandingBalance : $totalAmount, 2),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Place a new order for the customer.
     */
    public function placeCustomerOrder(Request $request)
    {
        $customer = $request->attributes->get('customer') ?: \App\Models\Customer::find($request->input('customer_id'));
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found or unauthorized.'], 401);
        }
        $customer = \App\Models\Customer::find($customer->id) ?: $customer;

        $now = Carbon::now();
        // Unpaid outstanding invoices from previous weeks (due date is strictly in the past)
        $outstandingInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['Pending', 'Unpaid'])
            ->whereDate('due_date', '<', $now->toDateString())
            ->get();

        $outstandingAmount = (float) $outstandingInvoices->sum('amount');

        if ($customer->status === 'Deactivated') {
            return response()->json([
                'success' => false,
                'message' => $outstandingAmount > 0
                    ? 'Your account is deactivated due to unpaid weekly invoices (AUD ' . number_format($outstandingAmount, 2) . '). You can pay your overdue balance anytime or contact admin to reactivate your account and continue ordering.'
                    : 'Your account is currently deactivated by the administrator. Please contact support to reactivate your account.',
                'has_overdue' => ($outstandingAmount > 0),
                'overdue_amount' => round($outstandingAmount, 2),
                'overdue_bill_id' => $outstandingInvoices->isNotEmpty() ? $outstandingInvoices->first()->id : null,
            ], 403);
        }

        // Normalize tiffin_plan_id or id if sent as tiffin_id
        if (!$request->has('tiffin_id') && $request->has('tiffin_plan_id')) {
            $request->merge(['tiffin_id' => $request->tiffin_plan_id]);
        }
        if (!$request->has('tiffin_id') && $request->has('id')) {
            $request->merge(['tiffin_id' => $request->id]);
        }

        // If custom_items are provided, automatically resolve/ensure the customizable tiffin if tiffin_id is missing or invalid
        $hasCustomItems = $request->filled('custom_items') || (is_array($request->input('custom_items')) && count($request->input('custom_items')) > 0);
        $tiffinId = $request->input('tiffin_id');

        if ($hasCustomItems && (!$tiffinId || $tiffinId === 'custom' || !is_numeric($tiffinId) || !\App\Models\Tiffin::where('id', $tiffinId)->exists())) {
            $customTiffin = \App\Models\Tiffin::where('is_customizable', true)->first()
                ?? \App\Models\Tiffin::where('name', 'like', '%custom%')->first();

            if (!$customTiffin) {
                $customTiffin = \App\Models\Tiffin::create([
                    'name' => 'Custom tiffin',
                    'description' => 'Build your own tiffin from today\'s active menu items.',
                    'price' => 0.00,
                    'prep_time' => '30 mins',
                    'status' => 'Active',
                    'is_customizable' => true,
                ]);
            }

            $request->merge(['tiffin_id' => $customTiffin->id]);
        }

        $validator = Validator::make($request->all(), [
            'tiffin_id' => 'required|exists:tiffins,id',
            'add_ons' => 'nullable',
            'adons' => 'nullable',
            'note' => 'nullable|string',
            'quantity' => 'nullable|integer|min:1',
            'selections' => 'nullable',
            'custom_items' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $tiffin = \App\Models\Tiffin::findOrFail($request->tiffin_id);
        $quantity = (int)$request->input('quantity', 1);

        // Resolve "Or" choices / Build-Your-Own items and work out the unit price.
        $selectionsData = null;
        $selectionDelta = 0.0;
        $hasExplicitCustomItems = $request->filled('custom_items') || $request->filled('items') || $request->filled('selections');

        if ($tiffin->is_customizable) {
            $picked = $request->input('custom_items')
                ?? $request->input('items')
                ?? $request->input('selections')
                ?? $request->input('add_ons')
                ?? $request->input('adons')
                ?? [];
            $resolved = $tiffin->resolveCustomItems($picked);
            if (!empty($resolved['errors'])) {
                return response()->json([
                    'success' => false,
                    'message' => implode(' ', $resolved['errors']),
                ], 422);
            }
            $customUnitPrice = $resolved['unit_price'];
            $selectionsData = [
                'mode' => 'custom',
                'custom_items' => $resolved['items'],
                'item_count' => $resolved['count'],
                'items_total' => $resolved['items_total'],
                'summary' => $resolved['summary'],
                'unit_price' => $resolved['unit_price'],
            ];
        } elseif (!empty($tiffin->components)) {
            $resolved = $tiffin->resolveSelections($request->input('selections', []));
            if (!empty($resolved['errors'])) {
                return response()->json([
                    'success' => false,
                    'message' => implode(' ', $resolved['errors']),
                ], 422);
            }
            $selectionDelta = $resolved['delta'];
            $selectionsData = [
                'choices' => $resolved['choices'],
                'summary' => $resolved['summary'],
                'unit_price' => round((float) $tiffin->price + $selectionDelta, 2),
            ];
        }

        $unitPrice = $customUnitPrice ?? ((float)$tiffin->price + $selectionDelta);
        $amount = ($unitPrice * $quantity);

        // Extract addon IDs from array or grouped object (e.g. adons: { salad: [...], roti: [...] })
        $rawAddons = $request->input('add_ons') ?? $request->input('adons');
        if ($tiffin->is_customizable && !$hasExplicitCustomItems) {
            $rawAddons = [];
        }
        $extractedAddonIds = [];

        if (is_array($rawAddons)) {
            $isAssoc = array_keys($rawAddons) !== range(0, count($rawAddons) - 1);
            if ($isAssoc) {
                foreach ($rawAddons as $groupKey => $groupItems) {
                    if (is_array($groupItems)) {
                        foreach ($groupItems as $itemEntry) {
                            if (is_array($itemEntry) && isset($itemEntry['id'])) {
                                $extractedAddonIds[] = (int)$itemEntry['id'];
                            } elseif (is_numeric($itemEntry)) {
                                $extractedAddonIds[] = (int)$itemEntry;
                            }
                        }
                    }
                }
            } else {
                foreach ($rawAddons as $itemEntry) {
                    if (is_array($itemEntry) && isset($itemEntry['id'])) {
                        $extractedAddonIds[] = (int)$itemEntry['id'];
                    } elseif (is_numeric($itemEntry)) {
                        $extractedAddonIds[] = (int)$itemEntry;
                    }
                }
            }
        }

        $addonsData = [];
        if (!empty($extractedAddonIds)) {
            foreach ($extractedAddonIds as $addonId) {
                $item = \App\Models\Item::find($addonId);
                if ($item) {
                    $amount += (float)$item->price;
                    $addonsData[] = [
                        'id' => $item->id,
                        'name' => $item->name,
                        'price' => $item->price,
                        'qty' => 1
                    ];
                }
            }
        }

        $orderId = 'ORD' . strtoupper(Str::random(8));

        $orderAddressInfo = AddressHelper::extractAndFormat($request, $customer);
        $orderStreet = $orderAddressInfo['street_address'];
        $orderCity = $orderAddressInfo['city'];
        $orderPincode = $orderAddressInfo['pincode'] ?: $customer->pincode;
        $orderFormattedAddress = $orderAddressInfo['address'] ?: $customer->address;

        $order = \App\Models\Order::create([
            'id' => $orderId,
            'customer_id' => $customer->id,
            'customer' => $customer->name,
            'customer_address' => $orderFormattedAddress,
            'street_address' => $orderStreet,
            'city' => $orderCity,
            'pincode' => $orderPincode,
            'tiffin_id' => $tiffin->id,
            'tiffin' => $tiffin->name,
            'quantity' => $quantity,
            'area' => $orderPincode,
            'amount' => $amount,
            'status' => 'Pending',
            'date' => Carbon::now()->toDateString(),
            'add_ons' => json_encode($addonsData),
            'selections' => $selectionsData,
            'note' => $request->input('note'),
            'payment_intent_id' => '',
        ]);

        // Consolidate into the customer's Weekly Bill / Invoice for this billing week
        $now = Carbon::now();
        $startOfWeek = $now->copy()->startOfWeek()->toDateString();
        $endOfWeek = $now->copy()->endOfWeek()->toDateString();
        $dueDate = $endOfWeek;
        $weekNumber = $now->weekOfYear;
        $year = $now->year;
        $weeklyInvPrefix = 'INV-W' . $year . str_pad((string) $weekNumber, 2, '0', STR_PAD_LEFT) . '-' . $customer->id;

        $weeklyOrders = \App\Models\Order::where('customer_id', $customer->id)
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->get();
        $totalWeeklyAmount = (float) $weeklyOrders->sum('amount');
        $weeklyOrderIds = $weeklyOrders->pluck('id')->toArray();
        $orderIdString = count($weeklyOrderIds) <= 3
            ? implode(', ', $weeklyOrderIds)
            : 'KP-W' . $year . '-' . count($weeklyOrderIds) . 'orders';

        $invoice = \App\Models\Invoice::where('customer_id', $customer->id)
            ->where(function ($q) use ($weeklyInvPrefix, $startOfWeek, $endOfWeek) {
                $q->where('id', 'like', $weeklyInvPrefix . '%')
                  ->orWhereBetween('due_date', [$startOfWeek, $endOfWeek]);
            })
            ->first();

        if (!$invoice) {
            $invoiceId = $weeklyInvPrefix . '-' . rand(100, 999);
            $invoice = \App\Models\Invoice::create([
                'id' => $invoiceId,
                'customer_id' => $customer->id,
                'order_id' => $orderIdString,
                'amount' => $totalWeeklyAmount,
                'status' => 'Pending',
                'due_date' => $dueDate,
            ]);
        } else {
            $invoice->amount = $totalWeeklyAmount;
            $invoice->order_id = $orderIdString;
            $invoice->due_date = $dueDate;
            if ($invoice->status === 'Paid') {
                $invoice->status = 'Pending';
            }
            $invoice->save();
        }

        $choicesLine = ($selectionsData && !empty($selectionsData['summary']))
            ? " Choices: {$selectionsData['summary']}."
            : '';

        \App\Models\Notification::create([
            'title' => 'New Order Placed',
            'message' => "Customer {$customer->name} placed order {$order->id} (AUD {$order->amount}).{$choicesLine} Weekly bill {$invoice->id} updated (Total AUD " . number_format($totalWeeklyAmount, 2) . ").",
            'user_type' => 'admin',
            'user_id' => null,
            'read_status' => false
        ]);

        FcmService::sendToAdmin(
            'New Order Placed',
            "Customer {$customer->name} placed order {$order->id} (AUD {$order->amount}).{$choicesLine}",
            ['order_id' => $order->id, 'type' => 'new_order']
        );

        \App\Models\Notification::create([
            'title' => 'Order Placed Successfully',
            'message' => "Your order {$order->id} for {$order->tiffin} (AUD {$order->amount}) has been placed successfully. Weekly bill total: AUD " . number_format($totalWeeklyAmount, 2) . ".",
            'user_type' => 'customer',
            'user_id' => $customer->id,
            'read_status' => false
        ]);

        FcmService::sendToCustomer(
            $customer->id,
            'Order Placed Successfully',
            "Your order {$order->id} for {$order->tiffin} (AUD {$order->amount}) has been placed successfully.",
            ['order_id' => $order->id, 'type' => 'order_placed']
        );

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully. Weekly bill updated.',
            'order' => $order,
            'invoice' => $invoice,
            'weekly_bill' => $invoice,
            'total_weekly_amount' => $totalWeeklyAmount,
            'stripe_client_secret' => '',
            'payment_intent_id' => '',
        ]);
    }

    public function confirmCustomerOrder(Request $request, $id)
    {
        $customer = $request->attributes->get('customer');
        $order = \App\Models\Order::where('customer_id', $customer->id)->findOrFail($id);

        if ($order->status === 'Payment Pending') {
            $order->status = 'Pending';
            $order->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Order confirmed and sent to kitchen.',
            'order' => $order
        ]);
    }

    public function payWeeklyBill(Request $request)
    {
        $customer = $request->attributes->get('customer') ?: \App\Models\Customer::find($request->input('customer_id'));
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or customer not found.',
            ], 401);
        }

        $billId = $request->input('bill_id') ?: $request->input('invoice_id') ?: $request->input('id');
        $resolved = $this->resolveWeeklyInvoicesForPayment($customer, $billId, $request);
        $totalAmount = (float) $resolved['amount'];

        if ($totalAmount <= 0) {
            $this->ensureInvoicesForCustomerOrders($customer);
            $resolved = $this->resolveWeeklyInvoicesForPayment($customer, $billId, $request);
            $totalAmount = (float) $resolved['amount'];
        }

        if ($totalAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No outstanding balance due for the selected bill.',
            ], 400);
        }

        $stripeSecret = config('services.stripe.secret') ?: env('STRIPE_SECRET');
        $paymentIntentId = '';
        $clientSecret = '';

        if ($stripeSecret && $stripeSecret !== 'mock') {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $stripeSecret,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])->asForm()->post('https://api.stripe.com/v1/payment_intents', [
                    'amount' => (int) round($totalAmount * 100),
                    'currency' => 'aud',
                    'automatic_payment_methods[enabled]' => 'true',
                ]);

                if ($response->failed()) {
                    $errorData = $response->json();
                    $errorMessage = isset($errorData['error']['message']) ? $errorData['error']['message'] : 'Stripe PaymentIntent creation failed.';
                    return response()->json([
                        'success' => false,
                        'message' => 'Stripe error: ' . $errorMessage,
                    ], 400);
                }

                $stripeData = $response->json();
                $paymentIntentId = $stripeData['id'];
                $clientSecret = $stripeData['client_secret'];
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe connection error: ' . $e->getMessage(),
                ], 500);
            }
        } else {
            $paymentIntentId = 'pi_mock_' . strtolower(Str::random(16));
            $clientSecret = $paymentIntentId . '_secret_' . strtolower(Str::random(16));
        }

        $primaryInvoice = $resolved['invoices']->first();
        $isOverdue = $resolved['is_overdue'] ?? false;
        $isMultipleInvoices = $resolved['invoices']->count() > 1;

        // Preserve specific bill_id if provided by client, otherwise use primary invoice id or overdue/all
        $targetBillId = $billId
            ?: ($isOverdue ? 'overdue' : ($primaryInvoice ? $primaryInvoice->id : 'INV-WEEKLY'));

        return response()->json([
            'success' => true,
            'amount' => $totalAmount,
            'stripe_client_secret' => $clientSecret,
            'payment_intent_id' => $paymentIntentId,
            'label' => $resolved['label'],
            'week_range' => $resolved['week_range'],
            'weekly_bill_id' => $targetBillId,
            'bill_id' => $targetBillId,
            'invoice_id' => $targetBillId,
            'is_overdue' => $isOverdue,
            'invoices_count' => $resolved['invoices']->count(),
            'invoice_ids' => $resolved['invoices']->pluck('id')->toArray(),
        ]);
    }

    public function confirmWeeklyBillPayment(Request $request)
    {
        $customer = $request->attributes->get('customer') ?: \App\Models\Customer::find($request->input('customer_id'));
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or customer not found.',
            ], 401);
        }

        $paymentIntentId = $request->input('payment_intent_id');

        if (!$paymentIntentId) {
            return response()->json([
                'success' => false,
                'message' => 'Payment intent ID is required.',
            ], 400);
        }

        // Prevent Replay Attacks
        if (\App\Models\Payment::where('payment_intent_id', $paymentIntentId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This payment transaction has already been processed.',
            ], 400);
        }

        // Prevent mock payment intents in production environment
        if (app()->environment('production') && str_starts_with($paymentIntentId, 'pi_mock_')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payment intent.',
            ], 400);
        }

        $resolved = $this->resolveWeeklyInvoicesForPayment($customer, $request->input('bill_id') ?: $request->input('invoice_id'), $request);
        $invoices = $resolved['invoices'];
        $totalAmount = (float) $resolved['amount'];

        if ($totalAmount <= 0 && $invoices->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No outstanding balance to pay for the selected bill.',
            ], 400);
        }

        $stripeSecret = config('services.stripe.secret') ?: env('STRIPE_SECRET');
        $paymentCleared = false;
        $actualPaidAmount = 0.0;

        if ($stripeSecret && $stripeSecret !== 'mock' && !str_starts_with($paymentIntentId, 'pi_mock_')) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $stripeSecret,
                ])->get('https://api.stripe.com/v1/payment_intents/' . $paymentIntentId);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['status']) && in_array($data['status'], ['succeeded', 'processing', 'requires_capture'])) {
                        $paymentCleared = true;
                    } elseif (isset($data['status']) && in_array($data['status'], ['requires_payment_method', 'requires_confirmation', 'requires_action']) && app()->environment('local')) {
                        $paymentCleared = true;
                    }
                    if (isset($data['amount'])) {
                        $actualPaidAmount = ((float) $data['amount']) / 100.0;
                    }
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe connection error: ' . $e->getMessage(),
                ], 500);
            }
        } else {
            $paymentCleared = true;
        }

        if ($paymentCleared) {
            $settleAmount = $actualPaidAmount > 0 
                ? $actualPaidAmount 
                : ((float) ($request->input('amount') ?: $totalAmount));

            // Settle all resolved invoices first
            $settledInvoices = collect([]);
            foreach ($invoices as $invoice) {
                $invoice->status = 'Paid';
                $invoice->save();
                $settledInvoices->push($invoice);
            }

            // FIFO Check: If settleAmount is greater than the sum of already settled invoices,
            // or if there are other unpaid overdue invoices and the paid amount covers them,
            // settle all remaining unpaid invoices in chronological order up to settleAmount.
            $alreadySettledSum = (float) $settledInvoices->sum('amount');
            $remainingPayment = $settleAmount - $alreadySettledSum;

            if ($remainingPayment > 0.01 || ($resolved['is_overdue'] ?? false)) {
                $otherUnpaidInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
                    ->whereIn('status', ['Pending', 'Unpaid'])
                    ->whereNotIn('id', $settledInvoices->pluck('id')->toArray())
                    ->orderBy('due_date', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->get();

                foreach ($otherUnpaidInvoices as $otherInv) {
                    if ($remainingPayment >= ((float) $otherInv->amount - 0.05) || (($resolved['is_overdue'] ?? false) && $settleAmount >= (float) $otherInv->amount)) {
                        $otherInv->status = 'Paid';
                        $otherInv->save();
                        $settledInvoices->push($otherInv);
                        $remainingPayment -= (float) $otherInv->amount;
                    }
                }
            }

            // Settle corresponding orders in the week/order_id for all settled invoices
            foreach ($settledInvoices as $sinv) {
                if ($sinv->order_id) {
                    $orderIds = array_filter(array_map('trim', explode(',', $sinv->order_id)));
                    if (!empty($orderIds)) {
                        \App\Models\Order::where('customer_id', $customer->id)
                            ->whereIn('id', $orderIds)
                            ->where('status', 'Payment Pending')
                            ->update(['status' => 'Pending']);
                    }
                }
                if ($sinv->due_date) {
                    $dt = Carbon::parse($sinv->due_date);
                    $wStart = $dt->copy()->startOfWeek()->toDateString();
                    $wEnd = $dt->copy()->endOfWeek()->toDateString();
                    \App\Models\Order::where('customer_id', $customer->id)
                        ->whereBetween('date', [$wStart, $wEnd])
                        ->where('status', 'Payment Pending')
                        ->update(['status' => 'Pending']);
                }
            }

            // Settle any remaining past Payment Pending orders
            if ($resolved['is_overdue'] ?? false) {
                \App\Models\Order::where('customer_id', $customer->id)
                    ->where('status', 'Payment Pending')
                    ->whereDate('date', '<=', Carbon::today()->toDateString())
                    ->update(['status' => 'Pending']);
            }

            $finalPaidAmount = $settleAmount > 0 ? $settleAmount : (float) $settledInvoices->sum('amount');
            $weekCount = $settledInvoices->count();
            $planLabel = $weekCount > 1 
                ? "Overdue Weekly Bills ({$weekCount} weeks)" 
                : ($resolved['label'] ?: 'Weekly Bill Payment');

            \App\Models\Payment::create([
                'id' => 'TXN' . strtoupper(Str::random(8)),
                'customer_id' => $customer->id,
                'customer' => $customer->name,
                'plan' => $planLabel,
                'amount' => $finalPaidAmount,
                'date' => Carbon::now()->toDateString(),
                'status' => 'Successful',
                'payment_intent_id' => $paymentIntentId,
            ]);

            // Reactivate customer account if all overdue invoices are settled
            $hasUnpaidOverdue = \App\Models\Invoice::where('customer_id', $customer->id)
                ->whereIn('status', ['Pending', 'Unpaid'])
                ->whereDate('due_date', '<', Carbon::now()->toDateString())
                ->exists();

            if (!$hasUnpaidOverdue) {
                $customer->status = 'Active';
                $customer->save();
            }

            // Admin Database Notification
            \App\Models\Notification::create([
                'title' => 'Weekly Bill Paid',
                'message' => "Customer {$customer->name} has paid AUD " . number_format($finalPaidAmount, 2) . " for {$planLabel}.",
                'user_type' => 'admin',
                'user_id' => null,
                'read_status' => false,
            ]);

            // Admin FCM Push Notification
            FcmService::sendToAdmin(
                'Weekly Bill Paid',
                "Customer {$customer->name} has paid AUD " . number_format($finalPaidAmount, 2) . " for {$planLabel}.",
                ['type' => 'weekly_bill_paid', 'customer_id' => (string)$customer->id, 'amount' => (string)$finalPaidAmount]
            );

            // Customer Database Notification
            \App\Models\Notification::create([
                'title' => 'Weekly Bill Paid',
                'message' => "Thank you! Your payment of AUD " . number_format($finalPaidAmount, 2) . " for {$planLabel} was successful. Your account is active.",
                'user_type' => 'customer',
                'user_id' => $customer->id,
                'read_status' => false,
            ]);

            // Customer FCM Push Notification
            FcmService::sendToCustomer(
                $customer->id,
                'Weekly Bill Paid',
                "Your weekly payment of AUD " . number_format($finalPaidAmount, 2) . " was successful. Your account is active.",
                ['type' => 'payment_success']
            );

            // Fetch updated remaining balances
            $remainingUnpaid = \App\Models\Invoice::where('customer_id', $customer->id)
                ->whereIn('status', ['Pending', 'Unpaid'])
                ->sum('amount');
            $remainingOverdue = \App\Models\Invoice::where('customer_id', $customer->id)
                ->whereIn('status', ['Pending', 'Unpaid'])
                ->whereDate('due_date', '<', Carbon::now()->toDateString())
                ->sum('amount');

            return response()->json([
                'success' => true,
                'message' => 'Weekly bill payment confirmed successfully. Your account is active and you can continue ordering.',
                'amount_paid' => $finalPaidAmount,
                'outstanding_balance' => round((float) $remainingUnpaid, 2),
                'overdue_balance' => round((float) $remainingOverdue, 2),
                'weekly_balance' => 0.00,
                'account_status' => $customer->status,
                'can_order' => ($customer->status === 'Active'),
                'settled_invoices_count' => $settledInvoices->count(),
                'settled_invoice_ids' => $settledInvoices->pluck('id')->toArray(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment verification failed.',
        ], 400);
    }

    /**
     * Get invoices and weekly balances for the authenticated customer.
     */
    public function customerInvoices(Request $request)
    {
        $customer = $request->attributes->get('customer');

        // Ensure physical weekly invoice records exist for all orders
        $this->ensureInvoicesForCustomerOrders($customer);

        // Current week reference
        $now = Carbon::now();
        $startOfWeek = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->toDateString()
            : $now->copy()->startOfWeek()->toDateString();
        $endOfWeek = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->toDateString()
            : $now->copy()->endOfWeek()->toDateString();
        $dueDate = $now->copy()->endOfWeek()->toDateString();

        // Invoices query
        $invoicesQuery = \App\Models\Invoice::where('customer_id', $customer->id);

        if ($request->filled('status')) {
            $invoicesQuery->where('status', $request->status);
        }

        $invoices = $invoicesQuery->orderBy('created_at', 'desc')->get();

        // Total outstanding unpaid balance
        $allUnpaidInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['Pending', 'Unpaid'])
            ->get();
        $outstandingBalance = (float) $allUnpaidInvoices->sum('amount');

        // Overdue unpaid balance (due date is strictly in past)
        $overdueInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['Pending', 'Unpaid'])
            ->whereDate('due_date', '<', $now->toDateString())
            ->get();
        $overdueBalance = (float) $overdueInvoices->sum('amount');
        $hasOverdue = $overdueBalance > 0;
        $overdueBillId = $hasOverdue ? 'overdue' : null;
        $primaryOverdueId = $overdueInvoices->isNotEmpty() ? $overdueInvoices->first()->id : null;

        // Auto-reactivate customer if overdue balance cleared
        if (!$hasOverdue && $customer->status === 'Deactivated') {
            $customer->status = 'Active';
            $customer->save();
        }

        // Orders placed in this particular week
        $currentWeekOrders = $customer->orders()
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->get();
        $currentWeekTotalAmount = (float) $currentWeekOrders->sum('amount');
        $currentWeekOrdersCount = $currentWeekOrders->count();

        // Invoices for this particular week
        $currentWeekPrefix = 'INV-W' . Carbon::parse($startOfWeek)->year . str_pad((string) Carbon::parse($startOfWeek)->weekOfYear, 2, '0', STR_PAD_LEFT) . '-' . $customer->id;
        $currentWeekInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
            ->where(function ($q) use ($startOfWeek, $endOfWeek, $currentWeekPrefix) {
                $q->where('id', 'like', $currentWeekPrefix . '%')
                  ->orWhereBetween('due_date', [$startOfWeek, $endOfWeek]);
            })
            ->get();

        $currentWeekUnpaidInvoices = $currentWeekInvoices->whereIn('status', ['Pending', 'Unpaid']);
        $currentWeekBalance = (float) $currentWeekUnpaidInvoices->sum('amount');

        if ($currentWeekInvoices->isNotEmpty() && $currentWeekUnpaidInvoices->isEmpty()) {
            $currentWeekBalance = 0.00;
        }

        // Weekly billing history breakdown
        $weeklyData = [];
        $allOrders = $customer->orders()->orderBy('date', 'desc')->get();

        foreach ($allOrders as $order) {
            $dt = Carbon::parse($order->date);
            $wStart = $dt->startOfWeek()->toDateString();
            $wEnd = $dt->endOfWeek()->toDateString();
            $weekKey = $wStart . '_' . $wEnd;

            if (! isset($weeklyData[$weekKey])) {
                $weeklyData[$weekKey] = [
                    'week_range' => Carbon::parse($wStart)->format('d M Y') . ' - ' . Carbon::parse($wEnd)->format('d M Y'),
                    'start_date' => $wStart,
                    'end_date' => $wEnd,
                    'amount' => 0.00,
                    'orders_count' => 0,
                    'status' => 'Pending',
                    'due_date' => $wEnd,
                    'paid_date' => 'N/A',
                ];
            }

            $weeklyData[$weekKey]['amount'] += (float) $order->amount;
            $weeklyData[$weekKey]['orders_count']++;
        }

        // Also incorporate any invoices that might not have orders directly attached
        foreach ($invoices as $inv) {
            if ($inv->due_date) {
                $dt = Carbon::parse($inv->due_date);
            } elseif (preg_match('/INV-W(\d{4})(\d{2})/', $inv->id, $m)) {
                $dt = Carbon::now()->setISODate((int) $m[1], (int) $m[2]);
            } else {
                $dt = Carbon::parse($inv->created_at ?: now());
            }
            $wStart = $dt->copy()->startOfWeek()->toDateString();
            $wEnd = $dt->copy()->endOfWeek()->toDateString();
            $weekKey = $wStart . '_' . $wEnd;
            if (!isset($weeklyData[$weekKey])) {
                $weeklyData[$weekKey] = [
                    'week_range' => Carbon::parse($wStart)->format('d M Y') . ' - ' . Carbon::parse($wEnd)->format('d M Y'),
                    'start_date' => $wStart,
                    'end_date' => $wEnd,
                    'amount' => (float)$inv->amount,
                    'orders_count' => 0,
                    'status' => $inv->status,
                    'due_date' => $inv->due_date ?: $wEnd,
                    'paid_date' => $inv->status === 'Paid' ? Carbon::parse($inv->updated_at)->toDateString() : 'N/A',
                ];
            }
        }

        $weeklyHistory = array_values($weeklyData);

        foreach ($weeklyHistory as &$w) {
            $wStart = $w['start_date'];
            $wEnd = $w['end_date'];
            $dtStart = Carbon::parse($wStart);
            $wYear = $dtStart->year;
            $wWeekNum = $dtStart->weekOfYear;
            $prefix = 'INV-W' . $wYear . str_pad((string) $wWeekNum, 2, '0', STR_PAD_LEFT) . '-' . $customer->id;

            $wOrders = $customer->orders()->whereBetween('date', [$wStart, $wEnd])->get();
            $wOrderIds = $wOrders->pluck('id')->toArray();

            $wInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
                ->where(function ($q) use ($wOrderIds, $wStart, $wEnd, $prefix) {
                    $q->where('id', 'like', $prefix . '%')
                      ->orWhereBetween('due_date', [$wStart, $wEnd]);
                    if (! empty($wOrderIds)) {
                        $q->orWhereIn('order_id', $wOrderIds);
                    }
                })
                ->get();

            $unpaid = $wInvoices->whereIn('status', ['Pending', 'Unpaid']);
            $paid = $wInvoices->where('status', 'Paid');

            $weeklyInv = \App\Models\Invoice::where('customer_id', $customer->id)
                ->where(function ($q) use ($wOrderIds, $wStart, $wEnd, $prefix) {
                    $q->where('id', 'like', $prefix . '%')
                      ->orWhereBetween('due_date', [$wStart, $wEnd]);
                    if (!empty($wOrderIds)) {
                        $q->orWhereIn('order_id', $wOrderIds);
                    }
                })
                ->first();

            $totalWkAmount = (float) ($wOrders->isNotEmpty() ? $wOrders->sum('amount') : ($weeklyInv ? $weeklyInv->amount : $w['amount']));
            $orderIdString = count($wOrderIds) <= 3 ? implode(', ', $wOrderIds) : 'KP-W' . $wYear . '-' . count($wOrderIds) . 'orders';

            if (!$weeklyInv && $totalWkAmount > 0) {
                $weeklyInv = \App\Models\Invoice::create([
                    'id' => $prefix . '-001',
                    'customer_id' => $customer->id,
                    'order_id' => $orderIdString,
                    'amount' => $totalWkAmount,
                    'status' => Carbon::parse($wEnd)->lt(Carbon::today()) ? 'Unpaid' : 'Pending',
                    'due_date' => $wEnd,
                ]);
            }

            $wBillId = $weeklyInv ? $weeklyInv->id : ($prefix . '-001');
            $w['id'] = $wBillId;
            $w['weekly_bill_id'] = $wBillId;
            $w['bill_id'] = $wBillId;
            $w['invoice_id'] = $wBillId;
            $w['week_id'] = $wStart . '_' . $wEnd;
            $w['amount'] = $weeklyInv ? (float) $weeklyInv->amount : $totalWkAmount;

            if ($wInvoices->isNotEmpty() && $unpaid->isEmpty()) {
                $w['status'] = 'Paid';
                $latestPaidTime = $paid->max('updated_at');
                $w['paid_date'] = $latestPaidTime ? Carbon::parse($latestPaidTime)->toDateString() : $wEnd;
            } elseif ($unpaid->isNotEmpty()) {
                $isOverdue = Carbon::parse($wEnd)->lt(Carbon::today());
                $w['status'] = $isOverdue ? 'Unpaid' : 'Pending';
                $w['paid_date'] = 'N/A';
            } else {
                $w['status'] = 'Pending';
                $w['paid_date'] = 'N/A';
            }

            // Map all tiffin orders in this week for full-week itemized view
            $w['orders'] = $wOrders->map(function ($ord) use ($wBillId) {
                return [
                    'id' => $ord->id,
                    'date' => $ord->date,
                    'tiffin' => $ord->tiffin,
                    'quantity' => $ord->quantity ?: 1,
                    'amount' => (float) $ord->amount,
                    'status' => $ord->status,
                    'add_ons' => $ord->add_ons,
                    'selections' => $ord->selections,
                    'weekly_bill_id' => $wBillId,
                    'bill_id' => $wBillId,
                    'invoice_id' => $wBillId,
                ];
            })->values()->toArray();
        }
        unset($w);

        usort($weeklyHistory, function ($a, $b) {
            return strcmp($b['start_date'], $a['start_date']);
        });

        $currentWeekBillId = !empty($weeklyHistory) ? $weeklyHistory[0]['id'] : ('INV-W' . Carbon::now()->year . str_pad((string) Carbon::now()->weekOfYear, 2, '0', STR_PAD_LEFT) . '-' . $customer->id . '-001');

        return response()->json([
            'success' => true,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'account_status' => $customer->status,
            'can_order' => ($customer->status === 'Active') || !$hasOverdue,
            'outstanding_balance' => round($outstandingBalance, 2),
            'overdue_balance' => round($overdueBalance, 2),
            'overdue_amount' => round($overdueBalance, 2),
            'has_overdue' => $hasOverdue,
            'overdue_bill_id' => $overdueBillId,
            'primary_overdue_bill_id' => $primaryOverdueId,
            'overdue_invoices_count' => $overdueInvoices->count(),
            'overdue_bill_ids' => $overdueInvoices->pluck('id')->toArray(),
            'weekly_balance' => round($currentWeekBalance, 2),
            'total_amount_due' => round($outstandingBalance, 2),
            'current_week' => [
                'id' => $currentWeekBillId,
                'weekly_bill_id' => $currentWeekBillId,
                'bill_id' => $currentWeekBillId,
                'invoice_id' => $currentWeekBillId,
                'start_date' => $startOfWeek,
                'end_date' => $endOfWeek,
                'due_date' => $dueDate,
                'week_range' => Carbon::parse($startOfWeek)->format('d M Y') . ' - ' . Carbon::parse($endOfWeek)->format('d M Y'),
                'orders_count' => $currentWeekOrdersCount,
                'total_amount' => round($currentWeekTotalAmount, 2),
                'unpaid_amount' => round($currentWeekBalance, 2),
                'has_overdue' => $hasOverdue,
                'overdue_amount' => round($overdueBalance, 2),
                'overdue_bill_id' => $overdueBillId,
                'primary_overdue_bill_id' => $primaryOverdueId,
                'orders' => $currentWeekOrders,
            ],
            'invoices' => $invoices,
            'weekly_history' => $weeklyHistory,
            'weekly_bills' => $weeklyHistory,
            'weekly_billing' => $weeklyHistory,
        ]);
    }

    /**
     * Get details for a single weekly bill or invoice.
     */
    public function customerInvoiceDetails(Request $request, $id)
    {
        $customer = $request->attributes->get('customer') ?: \App\Models\Customer::find($request->input('customer_id'));
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or customer not found.',
            ], 401);
        }
        $resolved = $this->resolveWeeklyInvoicesForPayment($customer, $id, $request);

        $primaryInvoice = $resolved['invoices']->first();
        $billId = $primaryInvoice ? $primaryInvoice->id : $id;
        if (!str_starts_with($billId, 'INV-W') && !empty($resolved['start_date'])) {
            $dt = Carbon::parse($resolved['start_date']);
            $billId = 'INV-W' . $dt->year . str_pad((string)$dt->weekOfYear, 2, '0', STR_PAD_LEFT) . '-' . $customer->id . '-001';
        }

        $startDate = $resolved['start_date'] ?: ($primaryInvoice ? Carbon::parse($primaryInvoice->created_at)->startOfWeek()->toDateString() : Carbon::now()->startOfWeek()->toDateString());
        $endDate = $resolved['end_date'] ?: ($primaryInvoice ? Carbon::parse($primaryInvoice->created_at)->endOfWeek()->toDateString() : Carbon::now()->endOfWeek()->toDateString());

        $orders = $customer->orders()->whereBetween('date', [$startDate, $endDate])->get();
        if ($orders->isEmpty() && $primaryInvoice && $primaryInvoice->order_id) {
            $orderIds = array_filter(array_map('trim', explode(',', $primaryInvoice->order_id)));
            $orders = $customer->orders()->whereIn('id', $orderIds)->get();
        }

        $status = $resolved['invoices']->whereIn('status', ['Pending', 'Unpaid'])->isNotEmpty() ? 'Pending' : 'Paid';
        if ($status === 'Pending' && Carbon::parse($endDate)->lt(Carbon::today())) {
            $status = 'Unpaid';
        }

        $totalAmount = (float) ($resolved['amount'] > 0 ? $resolved['amount'] : ($orders->isNotEmpty() ? $orders->sum('amount') : ($primaryInvoice ? $primaryInvoice->amount : 0)));

        $invoiceData = [
            'id' => $billId,
            'weekly_bill_id' => $billId,
            'bill_id' => $billId,
            'invoice_id' => $billId,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'amount' => $totalAmount,
            'total_amount' => $totalAmount,
            'status' => $status,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'due_date' => $endDate,
            'week_range' => Carbon::parse($startDate)->format('d M Y') . ' - ' . Carbon::parse($endDate)->format('d M Y'),
            'orders_count' => $orders->count(),
            'orders' => $orders->map(function ($ord) use ($billId) {
                return [
                    'id' => $ord->id,
                    'date' => $ord->date,
                    'tiffin' => $ord->tiffin,
                    'quantity' => $ord->quantity ?: 1,
                    'amount' => (float) $ord->amount,
                    'status' => $ord->status,
                    'add_ons' => $ord->add_ons,
                    'selections' => $ord->selections,
                    'weekly_bill_id' => $billId,
                    'bill_id' => $billId,
                    'invoice_id' => $billId,
                ];
            })->values(),
        ];

        return response()->json([
            'success' => true,
            'invoice' => $invoiceData,
            'weekly_bill' => $invoiceData,
            'weekly_bill_id' => $billId,
            'bill_id' => $billId,
        ]);
    }

    public function customerNotifications(Request $request)
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $notifications = \App\Models\Notification::where('user_type', 'customer')
            ->where(function ($query) use ($customer) {
                $query->where('user_id', $customer->id)
                      ->orWhereNull('user_id');
            })
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
        // Normalize first_name and last_name into name if provided
        if ($request->has('first_name') && $request->has('last_name')) {
            $request->merge([
                'name' => trim($request->first_name . ' ' . $request->last_name)
            ]);
        } elseif ($request->has('name')) {
            $nameParts = explode(' ', $request->name, 2);
            $request->merge([
                'first_name' => $nameParts[0] ?? '',
                'last_name' => $nameParts[1] ?? ''
            ]);
        }

        // Normalize password confirmation
        if ($request->has('confirm_password') && !$request->has('password_confirmation')) {
            $request->merge(['password_confirmation' => $request->confirm_password]);
        }
        if ($request->has('password_confirmation') && !$request->has('confirm_password')) {
            $request->merge(['confirm_password' => $request->password_confirmation]);
        }

        // Normalize suburbs and postcode aliases
        if ($request->has('suburbs') && !$request->has('suburb')) {
            $request->merge(['suburb' => $request->suburbs]);
        }
        if ($request->has('suburb') && !$request->has('city')) {
            $request->merge(['city' => $request->suburb]);
        }
        if ($request->has('postcode') && !$request->has('pincode')) {
            $request->merge(['pincode' => $request->postcode]);
        }

        // Normalize license and vehicle field aliases from mobile apps
        if ($request->has('license_number') && !$request->has('license_no')) {
            $request->merge(['license_no' => $request->license_number]);
        }
        if ($request->has('vehicle_number') && !$request->has('vehicle_reg_no')) {
            $request->merge(['vehicle_reg_no' => $request->vehicle_number]);
        }

        // Normalize area and assigned_zip
        if ($request->has('postcode') && !$request->has('assigned_zip')) {
            $request->merge(['assigned_zip' => $request->postcode]);
        }
        if ($request->has('area') && !$request->has('assigned_zip')) {
            $request->merge(['assigned_zip' => $request->area]);
        }
        if ($request->has('delivery_area') && !$request->has('assigned_zip')) {
            $request->merge(['assigned_zip' => $request->delivery_area]);
        }

        // Extract structured address fields (street_address, city/suburb, pincode/postcode, address)
        $addressInfo = AddressHelper::extractAndFormat($request);
        $streetAddress = $addressInfo['street_address'];
        $city = $addressInfo['city'];
        $pincode = $addressInfo['pincode'];
        $formattedAddress = $addressInfo['address'];

        if ($formattedAddress && !$request->has('address')) {
            $request->merge(['address' => $formattedAddress]);
        }
        if ($pincode && !$request->has('assigned_zip')) {
            $request->merge(['assigned_zip' => $pincode]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required_without:first_name|string|max:255',
            'first_name' => 'required_without:name|string|max:255',
            'last_name' => 'required_without:name|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6|confirmed',
            'confirm_password' => 'required_without:password_confirmation|string|min:6',
            'password_confirmation' => 'required_without:confirm_password|string|min:6',
            'address' => 'nullable|string',
            'street_address' => 'nullable|string',
            'city' => 'nullable|string',
            'suburb' => 'nullable|string',
            'suburbs' => 'nullable|string',
            'town' => 'nullable|string',
            'pincode' => 'nullable|string|max:20',
            'postcode' => 'nullable|string|max:20',
            'license_no' => 'nullable|string|max:100',
            'license_expiry' => 'nullable|date',
            'vehicle_reg_no' => 'nullable|string|max:50',
            'assigned_zip' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:255',
            'license_copy_front' => 'nullable',
            'license_copy_back' => 'nullable',
            'profile_image' => 'nullable',
            'vehicle_reg_image' => 'nullable',
            'vehicle_registration_image' => 'nullable',
            'vehicle_reg_doc' => 'nullable',
        ], [
            'password.confirmed' => 'The password and confirm password do not match.',
            'confirm_password.required_without' => 'The confirm password field is required.',
            'password_confirmation.required_without' => 'The confirm password field is required.',
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

        $licenseFront = ImageUploadHelper::saveLicenseFront(
            $request,
            'license_front_drv_' . time()
        );

        $licenseBack = ImageUploadHelper::saveLicenseBack(
            $request,
            'license_back_drv_' . time()
        );

        $profileImage = ImageUploadHelper::saveProfileImage(
            $request,
            'profile_drv_' . time()
        );

        $vehicleRegImage = ImageUploadHelper::saveVehicleReg(
            $request,
            'vehicle_reg_drv_' . time()
        );

        $assignedZip = $request->assigned_zip ? trim($request->assigned_zip) : ($pincode ?: ($request->area ? trim($request->area) : null));

        $driver = \App\Models\Driver::create([
            'name' => trim($request->name),
            'phone' => trim($request->phone),
            'email' => $email,
            'password' => Hash::make($request->password),
            'street_address' => $streetAddress,
            'city' => $city,
            'pincode' => $pincode ?: ($assignedZip ?: null),
            'address' => $formattedAddress ?: ($request->address ? trim($request->address) : null),
            'license_no' => $request->license_no ? trim($request->license_no) : null,
            'license_copy_front' => $licenseFront,
            'license_copy_back' => $licenseBack,
            'profile_image' => $profileImage,
            'vehicle_reg_image' => $vehicleRegImage,
            'license_expiry' => $request->license_expiry ? trim($request->license_expiry) : null,
            'vehicle_reg_no' => $request->vehicle_reg_no ? trim($request->vehicle_reg_no) : null,
            'assigned_zip' => $assignedZip,
            'area' => $assignedZip,
            // No API token and not active until an admin approves the profile.
            'api_token' => null,
            'status' => 'Inactive',
            'approval_status' => 'Pending',
            'user_type' => 'driver',
            'fcm_token' => $request->input('fcm_token'),
        ]);

        // Alert the admin dashboard that a driver is waiting for review.
        try {
            \App\Models\Notification::create([
                'title' => 'New Driver Awaiting Approval',
                'message' => "{$driver->name} ({$driver->email}) has registered as a driver and is awaiting admin approval.",
                'user_type' => 'admin',
                'user_id' => null,
                'read_status' => false,
            ]);
            FcmService::sendToAdmin(
                'New Driver Awaiting Approval',
                "{$driver->name} ({$driver->email}) has registered as a driver and is awaiting admin approval.",
                ['type' => 'driver_registration', 'driver_id' => $driver->id]
            );
        } catch (\Exception $e) {
            Log::warning("Driver approval notification failed: " . $e->getMessage());
        }

        try {
            Mail::to($driver->email)->send(new KitchenAlertMail("Registration received - pending approval", "Thank you {$driver->name} for registering with KP's Kitchen. Your profile has been submitted and is now pending review by our team. You will be able to log in once an admin approves your account."));
            Mail::to('admin@kpkitchen.com')->send(new KitchenAlertMail("New Driver Awaiting Approval", "Driver {$driver->name} ({$driver->email}) has registered and needs review in the admin panel."));
        } catch (\Exception $e) {
            Log::warning("Driver registration email triggers failed: " . $e->getMessage());
        }

        $vehicleRegUrl = $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null;

        $driverData = array_merge([
            'id' => $driver->id,
            'name' => $driver->name,
            'first_name' => $driver->first_name,
            'last_name' => $driver->last_name,
            'email' => $driver->email,
            'phone' => $driver->phone,
            'vehicle_reg_no' => $driver->vehicle_reg_no,
            'vehicle_number' => $driver->vehicle_reg_no,
            'license_no' => $driver->license_no,
            'license_number' => $driver->license_no,
            'license_expiry' => $driver->license_expiry,
            'license_copy_front' => $driver->license_copy_front ? (str_starts_with($driver->license_copy_front, 'http') ? $driver->license_copy_front : asset($driver->license_copy_front)) : null,
            'license_copy_back' => $driver->license_copy_back ? (str_starts_with($driver->license_copy_back, 'http') ? $driver->license_copy_back : asset($driver->license_copy_back)) : null,
            'profile_image' => $driver->profile_image ? (str_starts_with($driver->profile_image, 'http') ? $driver->profile_image : asset($driver->profile_image)) : null,
            'vehicle_reg_image' => $vehicleRegUrl,
            'vehicle_registration_image' => $vehicleRegUrl,
            'vehicle_reg_doc' => $vehicleRegUrl,
            'assigned_zip' => $driver->assigned_zip,
            'area' => $driver->area,
            'approval_status' => $driver->approval_status,
            'status' => 'pending',
            'user_type' => $driver->user_type,
        ], AddressHelper::formatResponsePayload($driver));

        return response()->json([
            'success' => true,
            'message' => 'Registration submitted. Your account is pending admin approval - you will be able to log in once it is approved.',
            'token' => null,
            'approval_status' => $driver->approval_status,
            'user_type' => $driver->user_type,
            'driver' => $driverData,
            'data' => [
                'driver' => $driverData,
                'user' => $driverData,
                'approval_status' => $driver->approval_status,
            ]
        ], 201);
    }

    /**
     * Handle driver login.
     */
    public function driverLogin(Request $request)
    {
        $loginInput = trim($request->input('email') ?: $request->input('phone') ?: $request->input('username') ?: '');
        $password = $request->input('password');

        if (empty($loginInput) || empty($password)) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide email or phone and password.',
            ], 422);
        }

        $driver = \App\Models\Driver::where('email', strtolower($loginInput))
            ->orWhere('phone', $loginInput)
            ->first();

        if (!$driver || !Hash::check($password, $driver->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // Gate login on admin approval.
        if ($driver->approval_status === 'Pending') {
            return response()->json([
                'success' => false,
                'approval_status' => 'Pending',
                'message' => 'Your account is awaiting admin approval. Please try again once it has been reviewed.',
            ], 403);
        }

        if ($driver->approval_status === 'Rejected') {
            return response()->json([
                'success' => false,
                'approval_status' => 'Rejected',
                'message' => $driver->rejection_reason
                    ? 'Your registration was not approved: ' . $driver->rejection_reason
                    : 'Your registration was not approved. Please contact KP\'s Kitchen for details.',
            ], 403);
        }

        $token = Str::random(60);
        $updateFields = ['api_token' => $token];
        if ($request->filled('fcm_token')) {
            $updateFields['fcm_token'] = $request->fcm_token;
        }
        $driver->update($updateFields);

        // Dedicated Driver Login Notification
        \App\Models\Notification::create([
            'title' => 'Login Successful',
            'message' => "Welcome back, {$driver->name}! You have successfully logged in.",
            'user_type' => 'driver',
            'user_id' => $driver->id,
            'read_status' => false,
        ]);

        FcmService::sendToDriver(
            $driver->id,
            'Login Successful',
            "Welcome back, {$driver->name}! You have successfully logged in.",
            ['type' => 'login']
        );

        try {
            Mail::to('admin@kpkitchen.com')->send(new KitchenAlertMail("User Login Notification", "{$driver->name} has logged in into KP's Kitchen."));
        } catch (\Exception $e) {
            Log::warning("Driver login alert email failed: " . $e->getMessage());
        }

        $vehicleRegUrl = $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null;

        $driverData = array_merge([
            'id' => $driver->id,
            'name' => $driver->name,
            'first_name' => $driver->first_name,
            'last_name' => $driver->last_name,
            'email' => $driver->email,
            'phone' => $driver->phone,
            'vehicle_reg_no' => $driver->vehicle_reg_no,
            'vehicle_number' => $driver->vehicle_reg_no,
            'license_no' => $driver->license_no,
            'license_number' => $driver->license_no,
            'license_expiry' => $driver->license_expiry,
            'license_copy_front' => $driver->license_copy_front ? (str_starts_with($driver->license_copy_front, 'http') ? $driver->license_copy_front : asset($driver->license_copy_front)) : null,
            'license_copy_back' => $driver->license_copy_back ? (str_starts_with($driver->license_copy_back, 'http') ? $driver->license_copy_back : asset($driver->license_copy_back)) : null,
            'profile_image' => $driver->profile_image ? (str_starts_with($driver->profile_image, 'http') ? $driver->profile_image : asset($driver->profile_image)) : null,
            'vehicle_reg_image' => $vehicleRegUrl,
            'vehicle_registration_image' => $vehicleRegUrl,
            'vehicle_reg_doc' => $vehicleRegUrl,
            'assigned_zip' => $driver->assigned_zip,
            'area' => $driver->area,
            'approval_status' => $driver->approval_status,
            'status' => 'active', // Lowercase for frontend condition matching
            'account_status' => $driver->status ?? 'Active',
            'user_type' => $driver->user_type,
        ], AddressHelper::formatResponsePayload($driver));

        return response()->json([
            'success' => true,
            'message' => 'Driver login successful.',
            'token' => $token,
            'user_type' => $driver->user_type,
            'driver' => $driverData,
            'data' => [
                'token' => $token,
                'driver' => $driverData,
                'user' => $driverData,
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
        $vehicleRegUrl = $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null;

        $driverData = array_merge([
            'id' => $driver->id,
            'name' => $driver->name,
            'first_name' => $driver->first_name,
            'last_name' => $driver->last_name,
            'email' => $driver->email,
            'phone' => $driver->phone,
            'license_no' => $driver->license_no,
            'license_expiry' => $driver->license_expiry,
            'license_copy_front' => $driver->license_copy_front ? (str_starts_with($driver->license_copy_front, 'http') ? $driver->license_copy_front : asset($driver->license_copy_front)) : null,
            'license_copy_back' => $driver->license_copy_back ? (str_starts_with($driver->license_copy_back, 'http') ? $driver->license_copy_back : asset($driver->license_copy_back)) : null,
            'profile_image' => $driver->profile_image ? (str_starts_with($driver->profile_image, 'http') ? $driver->profile_image : asset($driver->profile_image)) : null,
            'vehicle_reg_no' => $driver->vehicle_reg_no,
            'vehicle_reg_image' => $vehicleRegUrl,
            'vehicle_registration_image' => $vehicleRegUrl,
            'vehicle_reg_doc' => $vehicleRegUrl,
            'assigned_zip' => $driver->assigned_zip,
            'area' => $driver->area,
            'status' => $driver->status,
            'approval_status' => $driver->approval_status,
            'user_type' => $driver->user_type,
            'total_assigned_orders' => $driver->orders()->count(),
            'active_shipments' => $driver->orders()->whereIn('status', ['Cooking', 'Dispatched'])->count(),
            'recent_deliveries' => $driver->orders()->latest()->take(5)->get(),
        ], AddressHelper::formatResponsePayload($driver));

        return response()->json([
            'success' => true,
            'driver' => $driverData
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
        if ($request->has('new_password')) {
            $request->merge(['password' => $request->new_password]);
        }
        if ($request->has('password_confirmation')) {
            $request->merge(['confirm_password' => $request->password_confirmation]);
        }
        if ($request->has('new_password_confirmation')) {
            $request->merge(['confirm_password' => $request->new_password_confirmation]);
        }

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
        if ($request->has('new_password')) {
            $request->merge(['password' => $request->new_password]);
        }
        if ($request->has('password_confirmation')) {
            $request->merge(['confirm_password' => $request->password_confirmation]);
        }
        if ($request->has('new_password_confirmation')) {
            $request->merge(['confirm_password' => $request->new_password_confirmation]);
        }

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
                'profile_image' => $user->profile_image ? asset($user->profile_image) : null,
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
            $selections = is_array($cart->selections) ? $cart->selections : null;
            $unitPrice = $selections['unit_price']
                ?? ($cart->tiffin ? (float) $cart->tiffin->price : null);

            return [
                'id' => $cart->id,
                'quantity' => $cart->quantity,
                'selections' => $selections['choices'] ?? [],
                'choices_summary' => $selections['summary'] ?? '',
                'is_custom' => ($selections['mode'] ?? null) === 'custom',
                'custom_items' => $selections['custom_items'] ?? [],
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice !== null ? round($unitPrice * $cart->quantity, 2) : null,
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

        // Normalize tiffin_id if sent as id
        if (!$request->has('tiffin_id') && $request->has('id')) {
            $request->merge(['tiffin_id' => $request->id]);
        }

        // If custom_items are provided, automatically resolve/ensure the customizable tiffin if tiffin_id is missing or invalid
        $hasCustomItems = $request->filled('custom_items') || (is_array($request->input('custom_items')) && count($request->input('custom_items')) > 0);
        $tiffinId = $request->input('tiffin_id');

        if ($hasCustomItems && (!$tiffinId || $tiffinId === 'custom' || !is_numeric($tiffinId) || !\App\Models\Tiffin::where('id', $tiffinId)->exists())) {
            $customTiffin = \App\Models\Tiffin::where('is_customizable', true)->first()
                ?? \App\Models\Tiffin::where('name', 'like', '%custom%')->first();

            if (!$customTiffin) {
                $customTiffin = \App\Models\Tiffin::create([
                    'name' => 'Custom tiffin',
                    'description' => 'Build your own tiffin from today\'s active menu items.',
                    'price' => 0.00,
                    'prep_time' => '30 mins',
                    'status' => 'Active',
                    'is_customizable' => true,
                ]);
            }

            $request->merge(['tiffin_id' => $customTiffin->id]);
        }

        $validator = Validator::make($request->all(), [
            'tiffin_id' => 'nullable|exists:tiffins,id',
            'item_id' => 'nullable|exists:items,id',
            'quantity' => 'nullable|integer|min:1',
            'selections' => 'nullable',
            'custom_items' => 'nullable|array',
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

        // Resolve the customer's "Or" choices / Build-Your-Own items for this plan.
        $selectionsPayload = null;
        if ($tiffinId) {
            $tiffinModel = \App\Models\Tiffin::find($tiffinId);

            if ($tiffinModel && $tiffinModel->is_customizable) {
                $picked = $request->input('custom_items', $request->input('selections', []));
                $resolved = $tiffinModel->resolveCustomItems($picked);

                if (!empty($resolved['errors'])) {
                    return response()->json([
                        'success' => false,
                        'message' => implode(' ', $resolved['errors']),
                    ], 422);
                }

                $selectionsPayload = [
                    'mode' => 'custom',
                    'custom_items' => $resolved['items'],
                    'item_count' => $resolved['count'],
                    'items_total' => $resolved['items_total'],
                    'summary' => $resolved['summary'],
                    'unit_price' => $resolved['unit_price'],
                ];
            } elseif ($tiffinModel && !empty($tiffinModel->components)) {
                $rawSelections = $request->input('selections', []);
                $resolved = $tiffinModel->resolveSelections($rawSelections);

                if (!empty($resolved['errors'])) {
                    return response()->json([
                        'success' => false,
                        'message' => implode(' ', $resolved['errors']),
                    ], 422);
                }

                $selectionsPayload = [
                    'choices' => $resolved['choices'],
                    'summary' => $resolved['summary'],
                    'unit_price' => round((float) $tiffinModel->price + $resolved['delta'], 2),
                ];
            }
        }

        $sameSelections = function ($stored) use ($selectionsPayload) {
            if (($selectionsPayload['mode'] ?? null) === 'custom') {
                $a = is_array($stored) ? ($stored['custom_items'] ?? null) : null;
                return json_encode($a) === json_encode($selectionsPayload['custom_items']);
            }
            $a = is_array($stored) ? ($stored['choices'] ?? []) : [];
            $b = $selectionsPayload['choices'] ?? [];
            return json_encode($a) === json_encode($b);
        };

        if ($customerId) {
            $cartItem = \App\Models\Cart::where('customer_id', $customerId)
                ->where('tiffin_id', $tiffinId)
                ->where('item_id', $itemId)
                ->get()
                ->first(fn ($c) => $sameSelections($c->selections));

            if ($cartItem) {
                $cartItem->increment('quantity', $quantity);
            } else {
                $cartItem = \App\Models\Cart::create([
                    'customer_id' => $customerId,
                    'tiffin_id' => $tiffinId,
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'selections' => $selectionsPayload,
                ]);
            }
        } else {
            $cartItem = \App\Models\GuestCart::where('temp_user_id', $tempUserId)
                ->where('tiffin_id', $tiffinId)
                ->where('item_id', $itemId)
                ->get()
                ->first(fn ($c) => $sameSelections($c->selections));

            if ($cartItem) {
                $cartItem->increment('quantity', $quantity);
            } else {
                $cartItem = \App\Models\GuestCart::create([
                    'temp_user_id' => $tempUserId,
                    'tiffin_id' => $tiffinId,
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'selections' => $selectionsPayload,
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

        $orders = \App\Models\Order::with('customerRelation')
            ->where('driver_id', $driver->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                $customer = $order->customerRelation;
                $addons = is_string($order->add_ons) ? json_decode($order->add_ons, true) : ($order->add_ons ?? []);
                $cleanStatus = strtolower(str_replace(' ', '_', $order->status ?? 'pending'));

                $addrDetails = AddressHelper::extractAndFormat($order, $customer);
                $formattedOrderAddress = $addrDetails['address'];

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number ?: ('ORD-' . $order->id),
                    'customer_id' => $order->customer_id,
                    'customer' => $customer ? $customer->name : $order->customer,
                    'customer_name' => $customer ? $customer->name : ($order->customer ?? 'Customer'),
                    'customer_phone' => $customer ? $customer->phone : '',
                    'customer_address' => $formattedOrderAddress,
                    'delivery_address' => $formattedOrderAddress,
                    'formatted_address' => $formattedOrderAddress,
                    'street_address' => $addrDetails['street_address'],
                    'city' => $addrDetails['city'],
                    'suburb' => $addrDetails['city'],
                    'pincode' => $addrDetails['pincode'],
                    'postal_code' => $addrDetails['pincode'],
                    'address_details' => $addrDetails['address_details'],
                    'driver_id' => $order->driver_id,
                    'driver' => $order->driver,
                    'tiffin_id' => $order->tiffin_id,
                    'tiffin' => $order->tiffin,
                    'quantity' => $order->quantity,
                    'amount' => $order->amount,
                    'total_amount' => (string)$order->amount,
                    'status' => $cleanStatus,
                    'display_status' => $order->status,
                    'area' => $order->area,
                    'date' => $order->date,
                    'add_ons' => $addons,
                    'selections' => $order->selections,
                    'note' => $order->note,
                    'customer_note' => $order->note ?? '',
                    'proof_of_delivery_photo' => $order->proof_of_delivery_photo ? asset('public/' . ltrim($order->proof_of_delivery_photo, '/')) : null,
                    'proof_of_delivery_signature' => $order->proof_of_delivery_signature ? asset('public/' . ltrim($order->proof_of_delivery_signature, '/')) : null,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'orders' => $orders,
            'data' => [
                'data' => $orders,
                'orders' => $orders,
            ]
        ]);
    }

    /**
     * Get details of a single assigned order for the driver.
     */
    public function getDriverOrderDetails(Request $request, $id)
    {
        $driver = $request->attributes->get('driver');
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $order = \App\Models\Order::with('customerRelation')
            ->where('id', $id)
            ->where('driver_id', $driver->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or not assigned to this driver.'
            ], 404);
        }

        $customer = $order->customerRelation;
        $addons = is_string($order->add_ons) ? json_decode($order->add_ons, true) : ($order->add_ons ?? []);
        $cleanStatus = strtolower(str_replace(' ', '_', $order->status ?? 'pending'));

        $addrDetails = AddressHelper::extractAndFormat($order, $customer);
        $formattedOrderAddress = $addrDetails['address'];

        $orderData = [
            'id' => $order->id,
            'order_number' => $order->order_number ?: ('ORD-' . $order->id),
            'customer_id' => $order->customer_id,
            'customer_name' => $customer ? $customer->name : ($order->customer ?? 'Customer'),
            'customer_phone' => $customer ? $customer->phone : '',
            'customer_address' => $formattedOrderAddress,
            'delivery_address' => $formattedOrderAddress,
            'formatted_address' => $formattedOrderAddress,
            'street_address' => $addrDetails['street_address'],
            'city' => $addrDetails['city'],
            'suburb' => $addrDetails['city'],
            'pincode' => $addrDetails['pincode'],
            'postal_code' => $addrDetails['pincode'],
            'address_details' => $addrDetails['address_details'],
            'driver_id' => $order->driver_id,
            'tiffin_id' => $order->tiffin_id,
            'tiffin' => $order->tiffin,
            'quantity' => $order->quantity,
            'amount' => $order->amount,
            'total_amount' => (string)$order->amount,
            'status' => $cleanStatus,
            'display_status' => $order->status,
            'area' => $order->area,
            'date' => $order->date,
            'add_ons' => $addons,
            'selections' => $order->selections,
            'customer_note' => $order->note ?? '',
            'driver_note' => $order->driver_note ?? '',
            'proof_of_delivery_photo' => $order->proof_of_delivery_photo ? asset('public/' . ltrim($order->proof_of_delivery_photo, '/')) : null,
            'proof_of_delivery_signature' => $order->proof_of_delivery_signature ? asset('public/' . ltrim($order->proof_of_delivery_signature, '/')) : null,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];

        return response()->json([
            'success' => true,
            'order' => $orderData,
            'data' => [
                'order' => $orderData,
            ]
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

        $statusMap = [
            'delivered' => 'Delivered',
            'out_for_delivery' => 'Out for Delivery',
            'failed' => 'Failed',
            'pending' => 'Pending',
            'assigned' => 'Out for Delivery',
            'out for delivery' => 'Out for Delivery',
        ];
        $rawStatus = strtolower(trim(str_replace('_', ' ', $request->input('status', ''))));
        $normalizedStatus = $statusMap[$rawStatus] ?? ucfirst($request->input('status', 'Pending'));

        $order->status = $normalizedStatus;

        if ($request->filled('driver_note') || $request->filled('note')) {
            $noteText = $request->input('driver_note') ?: $request->input('note');
            $order->note = $order->note ? ($order->note . " | Driver: " . $noteText) : ("Driver: " . $noteText);
        }

        // Ensure pod directory exists
        $podDir = public_path('uploads/pod');
        if (!\Illuminate\Support\Facades\File::exists($podDir)) {
            \Illuminate\Support\Facades\File::makeDirectory($podDir, 0777, true, true);
        }

        // Handle proof of delivery photo upload (accept proof_photo, delivery_proof, photo, image, proof_of_delivery_photo, drop_photo, pod_photo, delivery_image, etc.)
        $photoFile = $request->file('proof_photo')
            ?: $request->file('delivery_proof')
            ?: $request->file('photo')
            ?: $request->file('image')
            ?: $request->file('proof_of_delivery_photo')
            ?: $request->file('drop_photo')
            ?: $request->file('pod_photo')
            ?: $request->file('drop_image')
            ?: $request->file('delivery_image')
            ?: $request->file('file')
            ?: $request->file('attachment');

        if ($photoFile) {
            $fileName = 'pod_photo_' . $order->id . '_' . time() . '.' . $photoFile->getClientOriginalExtension();
            $photoFile->move($podDir, $fileName);
            $order->proof_of_delivery_photo = 'uploads/pod/' . $fileName;
        } else {
            $base64Input = $request->input('proof_photo')
                ?: $request->input('delivery_proof')
                ?: $request->input('photo')
                ?: $request->input('image')
                ?: $request->input('proof_of_delivery_photo')
                ?: $request->input('drop_photo')
                ?: $request->input('pod_photo')
                ?: $request->input('drop_image');

            if ($base64Input && is_string($base64Input) && (str_starts_with($base64Input, 'data:image/') || strlen($base64Input) > 100)) {
                $imageType = 'jpg';
                $imageData = $base64Input;
                if (str_starts_with($base64Input, 'data:image/')) {
                    $parts = explode(';base64,', $base64Input);
                    $typeParts = explode('image/', $parts[0]);
                    $imageType = $typeParts[1] ?? 'jpg';
                    $imageData = $parts[1] ?? '';
                }
                $decoded = base64_decode($imageData);
                if ($decoded !== false) {
                    $fileName = 'pod_photo_' . $order->id . '_' . time() . '.' . $imageType;
                    \Illuminate\Support\Facades\File::put($podDir . '/' . $fileName, $decoded);
                    $order->proof_of_delivery_photo = 'uploads/pod/' . $fileName;
                }
            }
        }

        // Handle proof of delivery signature upload (file or base64)
        $sigFile = $request->file('proof_signature')
            ?: $request->file('signature')
            ?: $request->file('customer_signature');

        if ($sigFile) {
            $fileName = 'pod_sig_' . $order->id . '_' . time() . '.' . $sigFile->getClientOriginalExtension();
            $sigFile->move($podDir, $fileName);
            $order->proof_of_delivery_signature = 'uploads/pod/' . $fileName;
        } else {
            $sigInput = $request->input('proof_signature')
                ?: $request->input('signature')
                ?: $request->input('customer_signature');

            if ($sigInput && is_string($sigInput) && (str_starts_with($sigInput, 'data:image/') || strlen($sigInput) > 100)) {
                $imageType = 'png';
                $sigData = $sigInput;
                if (str_starts_with($sigInput, 'data:image/')) {
                    $parts = explode(';base64,', $sigInput);
                    $typeParts = explode('image/', $parts[0]);
                    $imageType = $typeParts[1] ?? 'png';
                    $sigData = $parts[1] ?? '';
                }
                $decoded = base64_decode($sigData);
                if ($decoded !== false) {
                    $fileName = 'pod_sig_' . $order->id . '_' . time() . '.' . $imageType;
                    \Illuminate\Support\Facades\File::put($podDir . '/' . $fileName, $decoded);
                    $order->proof_of_delivery_signature = 'uploads/pod/' . $fileName;
                }
            }
        }

        $order->save();

        \App\Models\Notification::create([
            'title' => 'Order Status Updated',
            'message' => "Driver {$driver->name} updated order {$order->id} status to {$order->status}.",
            'user_type' => 'admin',
            'user_id' => null,
            'read_status' => false
        ]);

        if ($order->customer_id) {
            \App\Models\Notification::create([
                'title' => 'Order Status Update',
                'message' => "Your order {$order->id} is now {$order->status}.",
                'user_type' => 'customer',
                'user_id' => $order->customer_id,
                'read_status' => false
            ]);

            FcmService::sendToCustomer(
                $order->customer_id,
                'Order Status Update',
                "Your order {$order->id} is now {$order->status}.",
                ['order_id' => $order->id, 'status' => $order->status, 'type' => 'order_status']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully.',
            'order' => $order,
            'data' => [
                'order' => $order,
            ]
        ]);
    }

    /**
     * Dedicated Driver POD Upload API: POST /driver/orders/upload-pod
     * Uploads proof of delivery image/signature and updates order status to Delivered.
     */
    public function driverUploadPod(Request $request, $id = null)
    {
        $orderId = $id ?: $request->input('order_id') ?: $request->input('id');
        if (!$orderId) {
            return response()->json([
                'success' => false,
                'message' => 'Order ID is required. Please provide order_id or id.'
            ], 422);
        }

        $driver = $request->attributes->get('driver');
        if (!$driver) {
            // Check Authorization Bearer Token
            $authHeader = $request->header('Authorization');
            if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                $driver = \App\Models\Driver::where('remember_token', $token)->first();
            }
            if (!$driver && $request->filled('driver_id')) {
                $driver = \App\Models\Driver::find($request->input('driver_id'));
            }
        }

        $query = \App\Models\Order::where('id', $orderId);
        if ($driver) {
            $order = (clone $query)->where(function ($q) use ($driver) {
                $q->where('driver_id', $driver->id)
                    ->orWhere('driver', $driver->name)
                    ->orWhereNull('driver_id')
                    ->orWhere('driver', 'Unassigned');
            })->first() ?: $query->first();
        } else {
            $order = $query->first();
        }

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        if ($driver && (!$order->driver_id || $order->driver_id != $driver->id)) {
            $order->driver_id = $driver->id;
            $order->driver = $driver->name;
        }

        $podDir = public_path('uploads/pod');
        if (!\Illuminate\Support\Facades\File::exists($podDir)) {
            \Illuminate\Support\Facades\File::makeDirectory($podDir, 0777, true, true);
        }

        // Handle proof of delivery photo upload
        $photoFile = $request->file('proof_photo')
            ?: $request->file('delivery_proof')
            ?: $request->file('photo')
            ?: $request->file('image')
            ?: $request->file('proof_of_delivery_photo')
            ?: $request->file('drop_photo')
            ?: $request->file('pod_photo')
            ?: $request->file('drop_image')
            ?: $request->file('delivery_image')
            ?: $request->file('file')
            ?: $request->file('attachment');

        if ($photoFile) {
            $fileName = 'pod_photo_' . $order->id . '_' . time() . '.' . $photoFile->getClientOriginalExtension();
            $photoFile->move($podDir, $fileName);
            $order->proof_of_delivery_photo = 'uploads/pod/' . $fileName;
        } else {
            $base64Input = $request->input('proof_photo')
                ?: $request->input('delivery_proof')
                ?: $request->input('photo')
                ?: $request->input('image')
                ?: $request->input('proof_of_delivery_photo')
                ?: $request->input('drop_photo')
                ?: $request->input('pod_photo')
                ?: $request->input('drop_image');

            if ($base64Input && is_string($base64Input) && (str_starts_with($base64Input, 'data:image/') || strlen($base64Input) > 100)) {
                $imageType = 'jpg';
                $imageData = $base64Input;
                if (str_starts_with($base64Input, 'data:image/')) {
                    $parts = explode(';base64,', $base64Input);
                    $typeParts = explode('image/', $parts[0]);
                    $imageType = $typeParts[1] ?? 'jpg';
                    $imageData = $parts[1] ?? '';
                }
                $decoded = base64_decode($imageData);
                if ($decoded !== false) {
                    $fileName = 'pod_photo_' . $order->id . '_' . time() . '.' . $imageType;
                    \Illuminate\Support\Facades\File::put($podDir . '/' . $fileName, $decoded);
                    $order->proof_of_delivery_photo = 'uploads/pod/' . $fileName;
                }
            }
        }

        // Handle signature upload (file or base64)
        $sigFile = $request->file('proof_signature')
            ?: $request->file('signature')
            ?: $request->file('customer_signature');

        if ($sigFile) {
            $fileName = 'pod_sig_' . $order->id . '_' . time() . '.' . $sigFile->getClientOriginalExtension();
            $sigFile->move($podDir, $fileName);
            $order->proof_of_delivery_signature = 'uploads/pod/' . $fileName;
        } else {
            $sigInput = $request->input('proof_signature')
                ?: $request->input('signature')
                ?: $request->input('customer_signature');

            if ($sigInput && is_string($sigInput) && (str_starts_with($sigInput, 'data:image/') || strlen($sigInput) > 100)) {
                $imageType = 'png';
                $sigData = $sigInput;
                if (str_starts_with($sigInput, 'data:image/')) {
                    $parts = explode(';base64,', $sigInput);
                    $typeParts = explode('image/', $parts[0]);
                    $imageType = $typeParts[1] ?? 'png';
                    $sigData = $parts[1] ?? '';
                }
                $decoded = base64_decode($sigData);
                if ($decoded !== false) {
                    $fileName = 'pod_sig_' . $order->id . '_' . time() . '.' . $imageType;
                    \Illuminate\Support\Facades\File::put($podDir . '/' . $fileName, $decoded);
                    $order->proof_of_delivery_signature = 'uploads/pod/' . $fileName;
                }
            }
        }

        if ($request->filled('driver_note') || $request->filled('note')) {
            $noteText = $request->input('driver_note') ?: $request->input('note');
            $order->note = $order->note ? ($order->note . " | Driver: " . $noteText) : ("Driver: " . $noteText);
        }

        // Update status to Delivered (or status passed by request)
        $newStatus = $request->input('status') ?: 'Delivered';
        $statusMap = [
            'delivered' => 'Delivered',
            'out_for_delivery' => 'Out for Delivery',
            'failed' => 'Failed',
            'pending' => 'Pending',
        ];
        $rawStatus = strtolower(trim(str_replace('_', ' ', $newStatus)));
        $order->status = $statusMap[$rawStatus] ?? ucfirst($newStatus);
        $order->save();

        // Notification for Admin
        $driverName = $driver ? $driver->name : ($order->driver ?: 'Driver');
        \App\Models\Notification::create([
            'title' => 'Order Delivered',
            'message' => "Driver {$driverName} delivered order {$order->id} and uploaded proof of delivery.",
            'user_type' => 'admin',
            'user_id' => null,
            'read_status' => false
        ]);

        // Notification for Customer
        if ($order->customer_id) {
            \App\Models\Notification::create([
                'title' => 'Order Delivered',
                'message' => "Your order {$order->id} has been delivered. Enjoy your meal!",
                'user_type' => 'customer',
                'user_id' => $order->customer_id,
                'read_status' => false
            ]);

            FcmService::sendToCustomer(
                $order->customer_id,
                'Order Delivered',
                "Your order {$order->id} has been delivered. Enjoy your meal!",
                ['order_id' => $order->id, 'status' => $order->status, 'type' => 'order_delivered']
            );
        }

        $photoUrl = $order->proof_of_delivery_photo ? asset('public/' . ltrim($order->proof_of_delivery_photo, '/')) : null;
        $sigUrl = $order->proof_of_delivery_signature ? asset('public/' . ltrim($order->proof_of_delivery_signature, '/')) : null;

        return response()->json([
            'success' => true,
            'message' => 'Proof of delivery uploaded successfully.',
            'proof_of_delivery_photo' => $order->proof_of_delivery_photo,
            'proof_of_delivery_photo_url' => $photoUrl,
            'proof_of_delivery_signature' => $order->proof_of_delivery_signature,
            'proof_of_delivery_signature_url' => $sigUrl,
            'order' => $order,
            'data' => [
                'order' => $order,
                'proof_of_delivery_photo' => $order->proof_of_delivery_photo,
                'proof_of_delivery_photo_url' => $photoUrl,
            ]
        ]);
    }

    public function editCustomerProfile(Request $request)
    {
        $customer = $request->attributes->get('customer');

        // Normalize first_name and last_name into name if provided
        if ($request->has('first_name') && $request->has('last_name')) {
            $request->merge([
                'name' => trim($request->first_name . ' ' . $request->last_name)
            ]);
        } elseif ($request->has('name')) {
            $nameParts = explode(' ', $request->name, 2);
            $request->merge([
                'first_name' => $nameParts[0] ?? '',
                'last_name' => $nameParts[1] ?? ''
            ]);
        }

        // Normalize new_password into password if provided
        if ($request->has('new_password')) {
            $request->merge([
                'password' => $request->new_password,
                'password_confirmation' => $request->new_password_confirmation ?? ''
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255|unique:customers,email,' . $customer->id,
            'pincode' => 'nullable|string|max:20',
            'postcode' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'street_address' => 'nullable|string',
            'city' => 'nullable|string',
            'suburb' => 'nullable|string',
            'town' => 'nullable|string',
            'old_password' => 'nullable|required_with:password|string',
            'password' => 'nullable|string|min:6|confirmed',
            'profile_image' => 'nullable',
            'image' => 'nullable',
            'avatar' => 'nullable',
            'photo' => 'nullable',
            'addresses' => 'nullable|array',
            'addresses.*.type' => 'required_with:addresses|string|max:50',
            'addresses.*.address_line' => 'nullable|string',
            'addresses.*.address' => 'nullable|string',
            'addresses.*.street_address' => 'nullable|string',
            'addresses.*.city' => 'nullable|string',
            'addresses.*.suburb' => 'nullable|string',
            'addresses.*.pincode' => 'nullable|string|max:20',
            'addresses.*.postcode' => 'nullable|string|max:20',
            'addresses.*.is_default' => 'required_with:addresses|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $data = [];

        if ($request->filled('name')) {
            $data['name'] = trim($request->name);
        }
        if ($request->filled('phone')) {
            $data['phone'] = trim($request->phone);
        }
        if ($request->filled('email')) {
            $data['email'] = strtolower(trim($request->email));
        }

        // If addresses array was supplied, extract default address or use the first one as primary
        if ($request->has('addresses') && is_array($request->addresses)) {
            $defaultAddr = null;
            foreach ($request->addresses as $addr) {
                if (filter_var($addr['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $defaultAddr = $addr;
                    break;
                }
            }
            if (!$defaultAddr && count($request->addresses) > 0) {
                $defaultAddr = $request->addresses[0];
            }

            if ($defaultAddr) {
                $addrInfo = AddressHelper::extractAndFormat($defaultAddr);
                $data['street_address'] = $addrInfo['street_address'];
                $data['city'] = $addrInfo['city'];
                $data['address'] = $addrInfo['address'];
                $data['pincode'] = $addrInfo['pincode'];
            }
        } else {
            if ($request->has('street_address') || $request->has('city') || $request->has('suburb') || $request->has('town') || $request->has('pincode') || $request->has('postcode') || $request->has('address')) {
                $addrInfo = AddressHelper::extractAndFormat($request, $customer);
                if ($addrInfo['street_address']) $data['street_address'] = $addrInfo['street_address'];
                if ($addrInfo['city']) $data['city'] = $addrInfo['city'];
                if ($addrInfo['pincode']) $data['pincode'] = $addrInfo['pincode'];
                if ($addrInfo['address']) $data['address'] = $addrInfo['address'];
            }
        }

        // Handle old password / new password validation
        if ($request->filled('password')) {
            if (!$request->filled('old_password')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error: Old password is required to change password.',
                ], 422);
            }
            if (!Hash::check($request->old_password, $customer->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The old password you entered is incorrect.'
                ], 422);
            }
            $data['password'] = Hash::make($request->password);
        }

        // Handle universal image upload (multipart or base64)
        $savedImage = $this->saveUploadedImage(
            $request,
            ['profile_image', 'image', 'avatar', 'photo', 'file'],
            'profile_cust_' . $customer->id,
            'uploads/profiles',
            $customer->profile_image
        );
        if ($savedImage !== null) {
            $data['profile_image'] = $savedImage;
        }

        $customer->update($data);
        $customer->refresh();

        // Process and sync addresses table
        if ($request->has('addresses') && is_array($request->addresses)) {
            $customer->addresses()->delete();
            foreach ($request->addresses as $addr) {
                $addrInfo = AddressHelper::extractAndFormat($addr);
                $customer->addresses()->create([
                    'type' => $addr['type'] ?? 'Home',
                    'street_address' => $addrInfo['street_address'],
                    'city' => $addrInfo['city'],
                    'address_line' => $addrInfo['address'],
                    'pincode' => $addrInfo['pincode'],
                    'is_default' => filter_var($addr['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }

        $profileImageUrl = $customer->profile_image ? (str_starts_with($customer->profile_image, 'http') ? $customer->profile_image : asset($customer->profile_image)) : null;

        $formattedAddresses = $customer->addresses()->orderBy('is_default', 'desc')->get()->map(function ($a) {
            return AddressHelper::formatAddressRecord($a);
        });

        $customerData = array_merge([
            'id' => $customer->id,
            'name' => $customer->name,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'profile_image' => $profileImageUrl,
            'user_type' => $customer->user_type,
            'addresses' => $formattedAddresses,
        ], AddressHelper::formatResponsePayload($customer));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'customer' => $customerData
        ]);
    }

    /**
     * Get all saved addresses for customer
     */
    public function getCustomerAddresses(Request $request)
    {
        $customer = $request->attributes->get('customer');
        if (!$customer && $request->filled('customer_id')) {
            $customer = Customer::find($request->input('customer_id'));
        }
        if (!$customer) {
            $token = $request->bearerToken() ?: $request->input('api_token');
            if ($token) {
                $customer = Customer::where('api_token', $token)->first();
            }
        }
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated or customer not found.'], 401);
        }

        $addresses = $customer->addresses()->orderBy('is_default', 'desc')->get();
        if ($addresses->isEmpty() && $customer->address) {
            $addrInfo = AddressHelper::extractAndFormat($customer);
            $created = $customer->addresses()->create([
                'type' => 'Home',
                'street_address' => $addrInfo['street_address'],
                'city' => $addrInfo['city'],
                'address_line' => $addrInfo['address'],
                'pincode' => $addrInfo['pincode'],
                'is_default' => true,
            ]);
            $addresses = collect([$created]);
        }

        $formatted = $addresses->map(fn($a) => AddressHelper::formatAddressRecord($a));

        return response()->json([
            'success' => true,
            'addresses' => $formatted
        ]);
    }

    /**
     * Add or update a customer address
     */
    public function addOrUpdateCustomerAddress(Request $request)
    {
        $customer = $request->attributes->get('customer');
        if (!$customer && $request->filled('customer_id')) {
            $customer = Customer::find($request->input('customer_id'));
        }
        if (!$customer) {
            $token = $request->bearerToken() ?: $request->input('api_token');
            if ($token) {
                $customer = Customer::where('api_token', $token)->first();
            }
        }
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated or customer not found.'], 401);
        }
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|exists:customer_addresses,id',
            'type' => 'required|string|max:50',
            'address_line' => 'nullable|string',
            'address' => 'nullable|string',
            'street_address' => 'nullable|string',
            'city' => 'nullable|string',
            'suburb' => 'nullable|string',
            'town' => 'nullable|string',
            'pincode' => 'nullable|string|max:20',
            'postcode' => 'nullable|string|max:20',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $addrInfo = AddressHelper::extractAndFormat($request);
        $streetAddress = $addrInfo['street_address'];
        $city = $addrInfo['city'];
        $pincode = $addrInfo['pincode'];
        $formattedAddress = $addrInfo['address'];

        $isDefault = filter_var($request->input('is_default', false), FILTER_VALIDATE_BOOLEAN);

        if ($isDefault) {
            $customer->addresses()->update(['is_default' => false]);
            $customer->update([
                'street_address' => $streetAddress,
                'city' => $city,
                'address' => $formattedAddress,
                'pincode' => $pincode
            ]);
        }

        if ($request->filled('id')) {
            $address = $customer->addresses()->findOrFail($request->id);
            $address->update([
                'type' => trim($request->type),
                'street_address' => $streetAddress,
                'city' => $city,
                'address_line' => $formattedAddress,
                'pincode' => $pincode,
                'is_default' => $isDefault,
            ]);
        } else {
            if ($customer->addresses()->count() === 0) {
                $isDefault = true;
                $customer->update([
                    'street_address' => $streetAddress,
                    'city' => $city,
                    'address' => $formattedAddress,
                    'pincode' => $pincode
                ]);
            }

            $address = $customer->addresses()->create([
                'type' => trim($request->type),
                'street_address' => $streetAddress,
                'city' => $city,
                'address_line' => $formattedAddress,
                'pincode' => $pincode,
                'is_default' => $isDefault,
            ]);
        }

        $formattedList = $customer->addresses()->orderBy('is_default', 'desc')->get()->map(fn($a) => AddressHelper::formatAddressRecord($a));

        return response()->json([
            'success' => true,
            'message' => 'Address saved successfully.',
            'address' => AddressHelper::formatAddressRecord($address),
            'addresses' => $formattedList
        ]);
    }

    /**
     * Delete a customer address
     */
    public function deleteCustomerAddress(Request $request, $id)
    {
        $customer = $request->attributes->get('customer');
        $address = $customer->addresses()->where('id', $id)->first();

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found.'
            ], 404);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $nextAddr = $customer->addresses()->first();
            if ($nextAddr) {
                $nextAddr->update(['is_default' => true]);
                $customer->update([
                    'address' => $nextAddr->address_line,
                    'pincode' => $nextAddr->pincode
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully.',
            'addresses' => $customer->addresses()->orderBy('is_default', 'desc')->get()
        ]);
    }

    public function clearCustomerNotifications(Request $request)
    {
        $customer = $request->attributes->get('customer');
        \App\Models\Notification::where('user_type', 'customer')
            ->where('user_id', $customer->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifications cleared successfully.'
        ]);
    }

    public function cancelCustomerOrder(Request $request, $id)
    {
        $customer = $request->attributes->get('customer');
        $order = \App\Models\Order::where('customer_id', $customer->id)->findOrFail($id);

        if (in_array($order->status, ['Delivered', 'Cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'This order cannot be cancelled as it is already ' . strtolower($order->status) . '.'
            ], 422);
        }

        $order->status = 'Cancelled';
        $order->save();

        \App\Models\Notification::create([
            'title' => 'Order Cancelled',
            'message' => "Order {$order->id} has been cancelled by customer {$customer->name}.",
            'user_type' => 'admin',
            'user_id' => null,
            'read_status' => false
        ]);

        FcmService::sendToAdmin(
            'Order Cancelled',
            "Order {$order->id} has been cancelled by customer {$customer->name}.",
            ['order_id' => $order->id, 'type' => 'order_cancelled']
        );

        \Illuminate\Support\Facades\Mail::to('admin@kpkitchen.com')->send(new \App\Mail\KitchenAlertMail("Order Cancellation Alert", "Order {$order->id} has been cancelled by customer {$customer->name}."));

        if ($order->driver_id) {
            \App\Models\Notification::create([
                'title' => 'Order Cancelled',
                'message' => "Order {$order->id} has been cancelled by the customer.",
                'user_type' => 'driver',
                'user_id' => $order->driver_id,
                'read_status' => false
            ]);

            FcmService::sendToDriver(
                $order->driver_id,
                'Order Cancelled',
                "Order {$order->id} has been cancelled by the customer.",
                ['order_id' => $order->id, 'type' => 'order_cancelled']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Order has been cancelled successfully.',
            'order' => $order
        ]);
    }

    public function editDriverProfile(Request $request)
    {
        $driver = $request->attributes->get('driver');
        if (!$driver && $request->filled('driver_id')) {
            $driver = Driver::find($request->input('driver_id'));
        }
        if (!$driver) {
            $token = $request->bearerToken() ?: $request->input('api_token');
            if ($token) {
                $driver = Driver::where('api_token', $token)->first();
            }
        }
        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated or driver not found.'], 401);
        }

        // Normalize first_name and last_name into name if provided
        if ($request->has('first_name') && $request->has('last_name')) {
            $request->merge([
                'name' => trim($request->first_name . ' ' . $request->last_name)
            ]);
        } elseif ($request->has('name')) {
            $nameParts = explode(' ', $request->name, 2);
            $request->merge([
                'first_name' => $nameParts[0] ?? '',
                'last_name' => $nameParts[1] ?? ''
            ]);
        }

        // Normalize new_password into password if provided
        if ($request->has('new_password')) {
            $request->merge([
                'password' => $request->new_password,
                'password_confirmation' => $request->new_password_confirmation ?? ''
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255|unique:drivers,email,' . $driver->id,
            'address' => 'nullable|string',
            'street_address' => 'nullable|string',
            'city' => 'nullable|string',
            'suburb' => 'nullable|string',
            'town' => 'nullable|string',
            'pincode' => 'nullable|string|max:20',
            'postcode' => 'nullable|string|max:20',
            'vehicle_reg_no' => 'nullable|string|max:50',
            'license_no' => 'nullable|string|max:100',
            'license_expiry' => 'nullable|date',
            'assigned_zip' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:255',
            'old_password' => 'nullable|required_with:password|string',
            'password' => 'nullable|string|min:6|confirmed',
            'profile_image' => 'nullable',
            'image' => 'nullable',
            'avatar' => 'nullable',
            'photo' => 'nullable',
            'license_copy_front' => 'nullable',
            'license_copy_back' => 'nullable',
            'vehicle_reg_image' => 'nullable',
            'vehicle_registration_image' => 'nullable',
            'vehicle_reg_doc' => 'nullable',
            'vehicle_doc' => 'nullable',
            'vehicle_image' => 'nullable',
            'rego_image' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $data = [];

        if ($request->filled('name')) {
            $data['name'] = trim($request->name);
        }
        if ($request->filled('phone')) {
            $data['phone'] = trim($request->phone);
        }
        if ($request->filled('email')) {
            $data['email'] = strtolower(trim($request->email));
        }

        if ($request->has('street_address') || $request->has('city') || $request->has('suburb') || $request->has('town') || $request->has('pincode') || $request->has('postcode') || $request->has('address') || $request->has('assigned_zip') || $request->has('area')) {
            $addrInfo = AddressHelper::extractAndFormat($request, $driver);
            if ($addrInfo['street_address']) $data['street_address'] = $addrInfo['street_address'];
            if ($addrInfo['city']) $data['city'] = $addrInfo['city'];
            if ($addrInfo['pincode']) {
                $data['pincode'] = $addrInfo['pincode'];
                $data['assigned_zip'] = $addrInfo['pincode'];
                $data['area'] = $addrInfo['pincode'];
            }
            if ($addrInfo['address']) $data['address'] = $addrInfo['address'];
        }

        if ($request->has('assigned_zip') && empty($data['assigned_zip'])) {
            $zip = trim($request->assigned_zip);
            $data['assigned_zip'] = $zip;
            $data['area'] = $zip;
            $data['pincode'] = $zip;
        }

        if ($request->has('vehicle_reg_no')) {
            $data['vehicle_reg_no'] = $request->vehicle_reg_no ? trim($request->vehicle_reg_no) : null;
        }
        if ($request->has('license_no')) {
            $data['license_no'] = $request->license_no ? trim($request->license_no) : null;
        }
        if ($request->has('license_expiry')) {
            $data['license_expiry'] = $request->license_expiry ? trim($request->license_expiry) : null;
        }
        if ($request->has('assigned_zip')) {
            $data['assigned_zip'] = $request->assigned_zip ? trim($request->assigned_zip) : null;
            $data['area'] = $data['assigned_zip'];
        } elseif ($request->has('area')) {
            $data['area'] = $request->area ? trim($request->area) : null;
            $data['assigned_zip'] = $data['area'];
        }

        // Handle old password / new password validation
        if ($request->filled('password')) {
            if (!$request->filled('old_password')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error: Old password is required to change password.',
                ], 422);
            }
            if (!Hash::check($request->old_password, $driver->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The old password you entered is incorrect.'
                ], 422);
            }
            $data['password'] = Hash::make($request->password);
        }

        // Handle Profile Image Upload
        $savedProfileImage = ImageUploadHelper::saveProfileImage(
            $request,
            'profile_drv_' . $driver->id,
            $driver->profile_image
        );
        if ($savedProfileImage !== null) {
            $data['profile_image'] = $savedProfileImage;
        }

        // Handle License Front Copy Upload
        $savedLicenseFront = ImageUploadHelper::saveLicenseFront(
            $request,
            'license_front_drv_' . $driver->id,
            $driver->license_copy_front
        );
        if ($savedLicenseFront !== null) {
            $data['license_copy_front'] = $savedLicenseFront;
        }

        // Handle License Back Copy Upload
        $savedLicenseBack = ImageUploadHelper::saveLicenseBack(
            $request,
            'license_back_drv_' . $driver->id,
            $driver->license_copy_back
        );
        if ($savedLicenseBack !== null) {
            $data['license_copy_back'] = $savedLicenseBack;
        }

        // Handle Vehicle Registration Document Upload
        $savedVehicleRegImage = ImageUploadHelper::saveVehicleReg(
            $request,
            'vehicle_reg_drv_' . $driver->id,
            $driver->vehicle_reg_image
        );
        if ($savedVehicleRegImage !== null) {
            $data['vehicle_reg_image'] = $savedVehicleRegImage;
        }

        $driver->update($data);
        $driver->refresh();

        $profileImageUrl = $driver->profile_image ? (str_starts_with($driver->profile_image, 'http') ? $driver->profile_image : asset($driver->profile_image)) : null;
        $licFrontUrl = $driver->license_copy_front ? (str_starts_with($driver->license_copy_front, 'http') ? $driver->license_copy_front : asset($driver->license_copy_front)) : null;
        $licBackUrl = $driver->license_copy_back ? (str_starts_with($driver->license_copy_back, 'http') ? $driver->license_copy_back : asset($driver->license_copy_back)) : null;
        $vehicleRegUrl = $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null;

        $driverData = array_merge([
            'id' => $driver->id,
            'name' => $driver->name,
            'first_name' => $driver->first_name,
            'last_name' => $driver->last_name,
            'email' => $driver->email,
            'phone' => $driver->phone,
            'license_no' => $driver->license_no,
            'license_expiry' => $driver->license_expiry,
            'license_copy_front' => $licFrontUrl,
            'license_copy_back' => $licBackUrl,
            'profile_image' => $profileImageUrl,
            'vehicle_reg_no' => $driver->vehicle_reg_no,
            'vehicle_reg_image' => $vehicleRegUrl,
            'vehicle_registration_image' => $vehicleRegUrl,
            'vehicle_reg_doc' => $vehicleRegUrl,
            'assigned_zip' => $driver->assigned_zip,
            'area' => $driver->area,
            'status' => $driver->status,
            'approval_status' => $driver->approval_status,
            'user_type' => $driver->user_type,
        ], AddressHelper::formatResponsePayload($driver));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'driver' => $driverData
        ]);
    }

    public function getDriverNotifications(Request $request)
    {
        $driver = $request->attributes->get('driver');
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $notifications = \App\Models\Notification::where('user_type', 'driver')
            ->where(function ($query) use ($driver) {
                $query->where('user_id', $driver->id)
                      ->orWhereNull('user_id');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'notifications' => $notifications
        ]);
    }

    public function clearDriverNotifications(Request $request)
    {
        $driver = $request->attributes->get('driver');
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        \App\Models\Notification::where('user_type', 'driver')
            ->where('user_id', $driver->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Driver notifications cleared successfully.'
        ]);
    }

    public function editAdminProfile(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $data = [
            'name' => trim($request->name),
            'email' => trim($request->email),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image && \Illuminate\Support\Facades\File::exists(public_path($user->profile_image))) {
                \Illuminate\Support\Facades\File::delete(public_path($user->profile_image));
            }
            $file = $request->file('profile_image');
            $fileName = 'profile_adm_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $uploadsDir = public_path('uploads/profiles');
            if (!\Illuminate\Support\Facades\File::exists($uploadsDir)) {
                \Illuminate\Support\Facades\File::makeDirectory($uploadsDir, 0777, true, true);
            }
            $file->move($uploadsDir, $fileName);
            $data['profile_image'] = 'uploads/profiles/' . $fileName;
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'admin' => $user
        ]);
    }

    /**
     * Helper to resolve target invoices and calculate total amount for a bill/week payment request.
     */
    private function resolveWeeklyInvoicesForPayment($customer, $billId = null, Request $request = null)
    {
        if (!$customer && $request) {
            $customer = $request->attributes->get('customer') ?: \App\Models\Customer::find($request->input('customer_id'));
        }
        if ($customer && is_numeric($customer)) {
            $customer = \App\Models\Customer::find($customer);
        }

        if (!$customer) {
            return [
                'invoices' => collect([]),
                'orders' => collect([]),
                'amount' => 0.00,
                'start_date' => null,
                'end_date' => null,
                'label' => 'Weekly Bill Payment',
                'week_range' => null,
                'is_overdue' => false,
            ];
        }

        $this->ensureInvoicesForCustomerOrders($customer);

        $rawBillId = $billId ? trim((string) $billId) : ($request ? trim((string) ($request->input('bill_id') ?: $request->input('invoice_id') ?: $request->input('id') ?: '')) : '');
        if (in_array(strtolower($rawBillId), ['undefined', 'null', '0', 'false', 'none', ''])) {
            $rawBillId = '';
        }
        $cleanBillId = strtolower($rawBillId);
        $reqAmount = $request ? (float) ($request->input('amount') ?: $request->input('total_amount') ?: 0) : 0.0;

        $startDate = $request ? $request->input('start_date', $request->input('week_start')) : null;
        $endDate = $request ? $request->input('end_date', $request->input('week_end')) : null;
        $now = Carbon::now();
        $nowDate = $now->toDateString();
        $thisWeekStart = $now->copy()->startOfWeek()->toDateString();
        $thisWeekEnd = $now->copy()->endOfWeek()->toDateString();

        // 1. Explicit Date Range in request
        if ($startDate && $endDate) {
            $invoices = \App\Models\Invoice::where('customer_id', $customer->id)
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('due_date', [$startDate, $endDate]);
                })
                ->get();
            $weeklyOrders = $customer->orders()->whereBetween('date', [$startDate, $endDate])->get();
            $unpaidInvoices = $invoices->whereIn('status', ['Pending', 'Unpaid']);
            $amount = $unpaidInvoices->isNotEmpty() ? (float) $unpaidInvoices->sum('amount') : (float) $weeklyOrders->sum('amount');
            $weekRange = Carbon::parse($startDate)->format('d M Y') . ' - ' . Carbon::parse($endDate)->format('d M Y');
            $isOverdue = Carbon::parse($endDate)->lt(Carbon::today()) && $unpaidInvoices->isNotEmpty();

            if ($amount > 0) {
                return [
                    'invoices' => $unpaidInvoices->isNotEmpty() ? $unpaidInvoices : $invoices,
                    'orders' => $weeklyOrders,
                    'amount' => $amount,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'label' => "Weekly Bill ({$weekRange})",
                    'week_range' => $weekRange,
                    'is_overdue' => $isOverdue,
                ];
            }
        }

        // 2. Explicit Current Week / New Bill
        if (in_array($cleanBillId, ['current', 'this_week', 'current_week', 'latest', 'new', 'new_bill', 'today', 'current_bill'])) {
            $weekInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
                ->where(function ($q) use ($thisWeekStart, $thisWeekEnd) {
                    $q->whereBetween('due_date', [$thisWeekStart, $thisWeekEnd]);
                })
                ->get();
            $weekOrders = $customer->orders()->whereBetween('date', [$thisWeekStart, $thisWeekEnd])->get();
            $unpaidInvoices = $weekInvoices->whereIn('status', ['Pending', 'Unpaid']);
            $amount = $unpaidInvoices->isNotEmpty() ? (float) $unpaidInvoices->sum('amount') : (float) $weekOrders->sum('amount');
            $weekRange = Carbon::parse($thisWeekStart)->format('d M Y') . ' - ' . Carbon::parse($thisWeekEnd)->format('d M Y');

            return [
                'invoices' => $unpaidInvoices->isNotEmpty() ? $unpaidInvoices : $weekInvoices,
                'orders' => $weekOrders,
                'amount' => $amount,
                'start_date' => $thisWeekStart,
                'end_date' => $thisWeekEnd,
                'label' => "Weekly Bill ({$weekRange})",
                'week_range' => $weekRange,
                'is_overdue' => false,
            ];
        }

        // 3. Explicit Total Due / All Unpaid / Combined
        if (in_array($cleanBillId, ['all', 'total', 'total_due', 'all_due', 'outstanding', 'all_unpaid', 'all_bills', 'combined', 'both'])) {
            $allUnpaid = \App\Models\Invoice::where('customer_id', $customer->id)
                ->whereIn('status', ['Pending', 'Unpaid'])
                ->orderBy('due_date', 'asc')
                ->get();
            $amount = (float) $allUnpaid->sum('amount');
            $hasOverdue = $allUnpaid->where('due_date', '<', $nowDate)->isNotEmpty();
            $weekCount = $allUnpaid->count();

            return [
                'invoices' => $allUnpaid,
                'orders' => collect([]),
                'amount' => $amount,
                'start_date' => null,
                'end_date' => null,
                'label' => $weekCount > 1 ? "Total Outstanding Balance ({$weekCount} weeks)" : 'Total Outstanding Balance',
                'week_range' => 'All Unpaid Weeks',
                'is_overdue' => $hasOverdue,
            ];
        }

        // 4. Explicit Overdue
        $isExplicitOverdue = ($request && ($request->input('is_overdue') === true || $request->input('is_overdue') === '1' || $request->input('is_overdue') === 'true' || $request->input('pay_type') === 'overdue' || $request->input('type') === 'overdue'))
            || in_array($cleanBillId, ['overdue', 'past_due', 'previous_overdue', 'previous', 'all_overdue', 'overdue_bills']);

        if ($isExplicitOverdue) {
            $overdueInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
                ->whereIn('status', ['Pending', 'Unpaid'])
                ->whereDate('due_date', '<', $nowDate)
                ->orderBy('due_date', 'asc')
                ->get();
            $amount = (float) $overdueInvoices->sum('amount');
            $weekCount = $overdueInvoices->count();

            return [
                'invoices' => $overdueInvoices,
                'orders' => collect([]),
                'amount' => $amount,
                'start_date' => null,
                'end_date' => null,
                'label' => $weekCount > 1 ? "Overdue Bills Payment ({$weekCount} weeks)" : 'Overdue Weekly Bill Payment',
                'week_range' => 'All Overdue Weeks',
                'is_overdue' => true,
            ];
        }

        // 5. Direct primary key match in invoices table
        if ($rawBillId) {
            $directInvoice = \App\Models\Invoice::where('customer_id', $customer->id)
                ->where('id', $rawBillId)
                ->first();

            if ($directInvoice) {
                $createdCarbon = Carbon::parse($directInvoice->due_date ?: $directInvoice->created_at);
                $wStart = $createdCarbon->copy()->startOfWeek()->toDateString();
                $wEnd = $createdCarbon->copy()->endOfWeek()->toDateString();
                $weekInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
                    ->where(function ($q) use ($wStart, $wEnd, $directInvoice) {
                        $q->where('id', $directInvoice->id)
                          ->orWhereBetween('due_date', [$wStart, $wEnd]);
                    })
                    ->get();
                $weekOrders = $customer->orders()->whereBetween('date', [$wStart, $wEnd])->get();
                $unpaidInvoices = $weekInvoices->whereIn('status', ['Pending', 'Unpaid']);
                $amount = $unpaidInvoices->isNotEmpty() ? (float) $unpaidInvoices->sum('amount') : ($directInvoice->status !== 'Paid' ? (float) $directInvoice->amount : 0.00);
                $isInvoiceOverdue = Carbon::parse($directInvoice->due_date ?: $directInvoice->created_at)->lt(Carbon::today());

                return [
                    'invoices' => $unpaidInvoices->isNotEmpty() ? $unpaidInvoices : collect([$directInvoice]),
                    'orders' => $weekOrders,
                    'amount' => $amount,
                    'start_date' => $wStart,
                    'end_date' => $wEnd,
                    'label' => "Weekly Bill Payment ({$directInvoice->id})",
                    'week_range' => Carbon::parse($wStart)->format('d M Y') . ' - ' . Carbon::parse($wEnd)->format('d M Y'),
                    'is_overdue' => $isInvoiceOverdue,
                ];
            }
        }

        // 6. Week-coded bill ID format e.g. "INV-W202638-1-001"
        if ($rawBillId && preg_match('/INV-W(\d{4})(\d{2})/i', $rawBillId, $wMatches)) {
            $wYear = (int) $wMatches[1];
            $wWeek = (int) $wMatches[2];
            $dt = Carbon::now()->setISODate($wYear, $wWeek);
            $wStart = $dt->copy()->startOfWeek()->toDateString();
            $wEnd = $dt->copy()->endOfWeek()->toDateString();

            $weekInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
                ->where(function ($q) use ($wStart, $wEnd, $rawBillId) {
                    $q->where('id', 'like', "%{$rawBillId}%")
                      ->orWhereBetween('due_date', [$wStart, $wEnd]);
                })
                ->get();
            $weekOrders = $customer->orders()->whereBetween('date', [$wStart, $wEnd])->get();
            $unpaidInvoices = $weekInvoices->whereIn('status', ['Pending', 'Unpaid']);
            $amount = $unpaidInvoices->isNotEmpty() ? (float) $unpaidInvoices->sum('amount') : (float) $weekOrders->sum('amount');
            $isOverdue = Carbon::parse($wEnd)->lt(Carbon::today());

            if ($amount > 0) {
                return [
                    'invoices' => $unpaidInvoices->isNotEmpty() ? $unpaidInvoices : $weekInvoices,
                    'orders' => $weekOrders,
                    'amount' => $amount,
                    'start_date' => $wStart,
                    'end_date' => $wEnd,
                    'label' => 'Weekly Bill (' . Carbon::parse($wStart)->format('d M Y') . ' - ' . Carbon::parse($wEnd)->format('d M Y') . ')',
                    'week_range' => Carbon::parse($wStart)->format('d M Y') . ' - ' . Carbon::parse($wEnd)->format('d M Y'),
                    'is_overdue' => $isOverdue,
                ];
            }
        }

        // 7. Match by week range string e.g. "2026-09-08_2026-09-14"
        if ($rawBillId && preg_match('/^(\d{4}-\d{2}-\d{2})[_\s\-to]+(\d{4}-\d{2}-\d{2})$/', $rawBillId, $matches)) {
            $wStart = $matches[1];
            $wEnd = $matches[2];
            $weekInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
                ->where(function ($q) use ($wStart, $wEnd) {
                    $q->whereBetween('due_date', [$wStart, $wEnd]);
                })
                ->get();
            $weekOrders = $customer->orders()->whereBetween('date', [$wStart, $wEnd])->get();
            $unpaidInvoices = $weekInvoices->whereIn('status', ['Pending', 'Unpaid']);
            $amount = $unpaidInvoices->isNotEmpty() ? (float) $unpaidInvoices->sum('amount') : (float) $weekOrders->sum('amount');
            $isOverdue = Carbon::parse($wEnd)->lt(Carbon::today());

            if ($amount > 0) {
                return [
                    'invoices' => $unpaidInvoices->isNotEmpty() ? $unpaidInvoices : $weekInvoices,
                    'orders' => $weekOrders,
                    'amount' => $amount,
                    'start_date' => $wStart,
                    'end_date' => $wEnd,
                    'label' => 'Weekly Bill (' . Carbon::parse($wStart)->format('d M Y') . ' - ' . Carbon::parse($wEnd)->format('d M Y') . ')',
                    'week_range' => Carbon::parse($wStart)->format('d M Y') . ' - ' . Carbon::parse($wEnd)->format('d M Y'),
                    'is_overdue' => $isOverdue,
                ];
            }
        }

        // 8. Match by order ID e.g. "ORD..."
        if ($rawBillId) {
            $matchingOrder = \App\Models\Order::where('customer_id', $customer->id)->where('id', $rawBillId)->first();
            if ($matchingOrder) {
                $dt = Carbon::parse($matchingOrder->date);
                $wStart = $dt->copy()->startOfWeek()->toDateString();
                $wEnd = $dt->copy()->endOfWeek()->toDateString();
                $weekInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
                    ->where(function ($q) use ($wStart, $wEnd, $matchingOrder) {
                        $q->where('order_id', 'like', "%{$matchingOrder->id}%")
                            ->orWhereBetween('due_date', [$wStart, $wEnd]);
                    })
                    ->get();
                $unpaidInvoices = $weekInvoices->whereIn('status', ['Pending', 'Unpaid']);
                $amount = $unpaidInvoices->isNotEmpty() ? (float) $unpaidInvoices->sum('amount') : (float) $matchingOrder->amount;
                $isOverdue = Carbon::parse($wEnd)->lt(Carbon::today());

                if ($amount > 0) {
                    return [
                        'invoices' => $unpaidInvoices->isNotEmpty() ? $unpaidInvoices : $weekInvoices,
                        'orders' => collect([$matchingOrder]),
                        'amount' => $amount,
                        'start_date' => $wStart,
                        'end_date' => $wEnd,
                        'label' => "Weekly Bill Payment ({$matchingOrder->tiffin})",
                        'week_range' => Carbon::parse($wStart)->format('d M Y') . ' - ' . Carbon::parse($wEnd)->format('d M Y'),
                        'is_overdue' => $isOverdue,
                    ];
                }
            }
        }

        // 9. Match with explicit request amount
        if ($reqAmount > 0) {
            // Check if matches current week
            $currOrders = $customer->orders()->whereBetween('date', [$thisWeekStart, $thisWeekEnd])->get();
            $currInvs = \App\Models\Invoice::where('customer_id', $customer->id)->whereBetween('due_date', [$thisWeekStart, $thisWeekEnd])->whereIn('status', ['Pending', 'Unpaid'])->get();
            $currAmount = $currInvs->isNotEmpty() ? (float)$currInvs->sum('amount') : (float)$currOrders->sum('amount');
            if (abs($reqAmount - $currAmount) < 0.01) {
                return [
                    'invoices' => $currInvs,
                    'orders' => $currOrders,
                    'amount' => $currAmount,
                    'start_date' => $thisWeekStart,
                    'end_date' => $thisWeekEnd,
                    'label' => "Weekly Bill (" . Carbon::parse($thisWeekStart)->format('d M Y') . " - " . Carbon::parse($thisWeekEnd)->format('d M Y') . ")",
                    'week_range' => Carbon::parse($thisWeekStart)->format('d M Y') . ' - ' . Carbon::parse($thisWeekEnd)->format('d M Y'),
                    'is_overdue' => false,
                ];
            }

            // Check if matches overdue
            $overdueInvs = \App\Models\Invoice::where('customer_id', $customer->id)->whereIn('status', ['Pending', 'Unpaid'])->whereDate('due_date', '<', $nowDate)->get();
            $overdueAmount = (float) $overdueInvs->sum('amount');
            if (abs($reqAmount - $overdueAmount) < 0.01) {
                return [
                    'invoices' => $overdueInvs,
                    'orders' => collect([]),
                    'amount' => $overdueAmount,
                    'start_date' => null,
                    'end_date' => null,
                    'label' => "Overdue Bills Payment (" . $overdueInvs->count() . " weeks)",
                    'week_range' => 'All Overdue Weeks',
                    'is_overdue' => true,
                ];
            }

            // Check if matches total unpaid
            $allUnpaid = \App\Models\Invoice::where('customer_id', $customer->id)->whereIn('status', ['Pending', 'Unpaid'])->get();
            $allAmount = (float) $allUnpaid->sum('amount');
            if (abs($reqAmount - $allAmount) < 0.01) {
                return [
                    'invoices' => $allUnpaid,
                    'orders' => collect([]),
                    'amount' => $allAmount,
                    'start_date' => null,
                    'end_date' => null,
                    'label' => "Total Outstanding Balance (" . $allUnpaid->count() . " weeks)",
                    'week_range' => 'All Unpaid Weeks',
                    'is_overdue' => $allUnpaid->where('due_date', '<', $nowDate)->isNotEmpty(),
                ];
            }

            // Custom amount
            return [
                'invoices' => $allUnpaid,
                'orders' => collect([]),
                'amount' => $reqAmount,
                'start_date' => null,
                'end_date' => null,
                'label' => 'Weekly Bill Payment',
                'week_range' => null,
                'is_overdue' => false,
            ];
        }

        // 10. Fallback: Overdue first, then Current Week, then All Unpaid
        $overdueInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['Pending', 'Unpaid'])
            ->whereDate('due_date', '<', $nowDate)
            ->orderBy('due_date', 'asc')
            ->get();

        if ($overdueInvoices->isNotEmpty()) {
            $weekCount = $overdueInvoices->count();
            return [
                'invoices' => $overdueInvoices,
                'orders' => collect([]),
                'amount' => (float) $overdueInvoices->sum('amount'),
                'start_date' => null,
                'end_date' => null,
                'label' => $weekCount > 1 ? "Overdue Bills Payment ({$weekCount} weeks)" : 'Overdue Weekly Bill Payment',
                'week_range' => 'All Overdue Weeks',
                'is_overdue' => true,
            ];
        }

        // Check current week
        $currOrders = $customer->orders()->whereBetween('date', [$thisWeekStart, $thisWeekEnd])->get();
        $currInvs = \App\Models\Invoice::where('customer_id', $customer->id)->whereBetween('due_date', [$thisWeekStart, $thisWeekEnd])->whereIn('status', ['Pending', 'Unpaid'])->get();
        $currAmount = $currInvs->isNotEmpty() ? (float)$currInvs->sum('amount') : (float)$currOrders->sum('amount');
        if ($currAmount > 0) {
            return [
                'invoices' => $currInvs,
                'orders' => $currOrders,
                'amount' => $currAmount,
                'start_date' => $thisWeekStart,
                'end_date' => $thisWeekEnd,
                'label' => "Weekly Bill (" . Carbon::parse($thisWeekStart)->format('d M Y') . " - " . Carbon::parse($thisWeekEnd)->format('d M Y') . ")",
                'week_range' => Carbon::parse($thisWeekStart)->format('d M Y') . ' - ' . Carbon::parse($thisWeekEnd)->format('d M Y'),
                'is_overdue' => false,
            ];
        }

        $allUnpaid = \App\Models\Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['Pending', 'Unpaid'])
            ->orderBy('due_date', 'asc')
            ->get();

        if ($allUnpaid->isNotEmpty()) {
            return [
                'invoices' => $allUnpaid,
                'orders' => collect([]),
                'amount' => (float) $allUnpaid->sum('amount'),
                'start_date' => null,
                'end_date' => null,
                'label' => 'Weekly Balance Payment',
                'week_range' => null,
                'is_overdue' => false,
            ];
        }

        return [
            'invoices' => collect([]),
            'orders' => collect([]),
            'amount' => 0.00,
            'start_date' => null,
            'end_date' => null,
            'label' => 'Weekly Bill Payment',
            'week_range' => null,
            'is_overdue' => false,
        ];
    }

    /**
     * Helper to ensure weekly invoice DB records exist for all customer orders across weeks.
     */
    private function ensureInvoicesForCustomerOrders($customer)
    {
        if (!$customer) return;
        $orders = $customer->orders()->get();
        $weeklyGroups = [];
        foreach ($orders as $order) {
            $dt = Carbon::parse($order->date);
            $wStart = $dt->copy()->startOfWeek()->toDateString();
            $wEnd = $dt->copy()->endOfWeek()->toDateString();
            $wYear = $dt->year;
            $wWeekNum = $dt->weekOfYear;
            $key = $wYear . '_' . $wWeekNum;
            if (!isset($weeklyGroups[$key])) {
                $weeklyGroups[$key] = [
                    'start' => $wStart,
                    'end' => $wEnd,
                    'year' => $wYear,
                    'week_num' => $wWeekNum,
                    'orders' => [],
                    'total' => 0.0,
                ];
            }
            $weeklyGroups[$key]['orders'][] = $order->id;
            $weeklyGroups[$key]['total'] += (float) $order->amount;
        }

        foreach ($weeklyGroups as $key => $grp) {
            $prefix = 'INV-W' . $grp['year'] . str_pad((string)$grp['week_num'], 2, '0', STR_PAD_LEFT) . '-' . $customer->id;
            $exists = \App\Models\Invoice::where('customer_id', $customer->id)
                ->where(function ($q) use ($prefix, $grp) {
                    $q->where('id', 'like', $prefix . '%')
                      ->orWhereBetween('due_date', [$grp['start'], $grp['end']]);
                })
                ->first();

            $orderIdStr = count($grp['orders']) <= 3 ? implode(', ', $grp['orders']) : 'KP-W' . $grp['year'] . '-' . count($grp['orders']) . 'orders';
            $isPast = Carbon::parse($grp['end'])->lt(Carbon::today());

            if (!$exists) {
                if ($grp['total'] > 0) {
                    \App\Models\Invoice::create([
                        'id' => $prefix . '-001',
                        'customer_id' => $customer->id,
                        'order_id' => $orderIdStr,
                        'amount' => $grp['total'],
                        'status' => $isPast ? 'Unpaid' : 'Pending',
                        'due_date' => $grp['end'],
                    ]);
                }
            } else {
                if (in_array($exists->status, ['Pending', 'Unpaid']) && (float)$exists->amount != (float)$grp['total'] && $grp['total'] > 0) {
                    $exists->amount = $grp['total'];
                    $exists->order_id = $orderIdStr;
                    $exists->save();
                }
            }
        }
    }

    public function createBillPaymentIntent(Request $request, $billId)
    {
        $customer = $request->attributes->get('customer') ?: \App\Models\Customer::find($request->input('customer_id'));
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or customer not found.',
            ], 401);
        }

        $resolved = $this->resolveWeeklyInvoicesForPayment($customer, $billId, $request);
        $totalAmount = (float) $resolved['amount'];

        if ($totalAmount <= 0) {
            $this->ensureInvoicesForCustomerOrders($customer);
            $resolved = $this->resolveWeeklyInvoicesForPayment($customer, $billId, $request);
            $totalAmount = (float) $resolved['amount'];
        }

        if ($totalAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No outstanding balance due for this bill.',
            ], 400);
        }

        $stripeSecret = config('services.stripe.secret') ?: env('STRIPE_SECRET');
        $paymentIntentId = '';
        $clientSecret = '';

        if ($stripeSecret && $stripeSecret !== 'mock') {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $stripeSecret,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])->asForm()->post('https://api.stripe.com/v1/payment_intents', [
                    'amount' => (int) round($totalAmount * 100),
                    'currency' => 'aud',
                    'automatic_payment_methods[enabled]' => 'true',
                ]);

                if ($response->failed()) {
                    $errorData = $response->json();
                    $errorMessage = isset($errorData['error']['message']) ? $errorData['error']['message'] : 'Stripe PaymentIntent creation failed.';
                    return response()->json([
                        'success' => false,
                        'message' => 'Stripe error: ' . $errorMessage,
                    ], 400);
                }

                $stripeData = $response->json();
                $paymentIntentId = $stripeData['id'];
                $clientSecret = $stripeData['client_secret'];
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe connection error: ' . $e->getMessage(),
                ], 500);
            }
        } else {
            $paymentIntentId = 'pi_mock_' . strtolower(Str::random(16));
            $clientSecret = $paymentIntentId . '_secret_' . strtolower(Str::random(16));
        }

        $primaryInvoice = $resolved['invoices']->first();
        $isOverdue = $resolved['is_overdue'] ?? false;
        $isMultipleInvoices = $resolved['invoices']->count() > 1;

        // Preserve specific bill_id if provided by client, otherwise use primary invoice id or overdue/all
        $targetBillId = $billId
            ?: ($isOverdue ? 'overdue' : ($primaryInvoice ? $primaryInvoice->id : 'INV-WEEKLY'));

        return response()->json([
            'success' => true,
            'amount' => $totalAmount,
            'stripe_client_secret' => $clientSecret,
            'payment_intent_id' => $paymentIntentId,
            'label' => $resolved['label'],
            'week_range' => $resolved['week_range'],
            'weekly_bill_id' => $targetBillId,
            'bill_id' => $targetBillId,
            'invoice_id' => $targetBillId,
            'is_overdue' => $isOverdue,
            'invoices_count' => $resolved['invoices']->count(),
            'invoice_ids' => $resolved['invoices']->pluck('id')->toArray(),
        ]);
    }

    public function confirmBillPayment(Request $request, $billId)
    {
        $customer = $request->attributes->get('customer') ?: \App\Models\Customer::find($request->input('customer_id'));
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or customer not found.',
            ], 401);
        }

        $paymentIntentId = $request->input('payment_intent_id');

        if (!$paymentIntentId) {
            return response()->json([
                'success' => false,
                'message' => 'Payment intent ID is required.',
            ], 400);
        }

        // Prevent Replay Attacks
        if (\App\Models\Payment::where('payment_intent_id', $paymentIntentId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This payment transaction has already been processed.',
            ], 400);
        }

        // Prevent mock payment intents in production environment
        if (app()->environment('production') && str_starts_with($paymentIntentId, 'pi_mock_')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payment intent.',
            ], 400);
        }

        $resolved = $this->resolveWeeklyInvoicesForPayment($customer, $billId, $request);
        $invoices = $resolved['invoices'];
        $totalAmount = (float) $resolved['amount'];

        if ($totalAmount <= 0 && $invoices->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Bill already marked as paid.',
            ]);
        }

        $stripeSecret = config('services.stripe.secret') ?: env('STRIPE_SECRET');
        $paymentCleared = false;
        $actualPaidAmount = 0.0;

        if ($stripeSecret && $stripeSecret !== 'mock' && !str_starts_with($paymentIntentId, 'pi_mock_')) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $stripeSecret,
                ])->get('https://api.stripe.com/v1/payment_intents/' . $paymentIntentId);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['status']) && in_array($data['status'], ['succeeded', 'processing', 'requires_capture'])) {
                        $paymentCleared = true;
                    } elseif (isset($data['status']) && in_array($data['status'], ['requires_payment_method', 'requires_confirmation', 'requires_action']) && app()->environment('local')) {
                        $paymentCleared = true;
                    }
                    if (isset($data['amount'])) {
                        $actualPaidAmount = ((float) $data['amount']) / 100.0;
                    }
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe connection error: ' . $e->getMessage(),
                ], 500);
            }
        } else {
            $paymentCleared = true;
        }

        if ($paymentCleared) {
            $settleAmount = $actualPaidAmount > 0 
                ? $actualPaidAmount 
                : ((float) ($request->input('amount') ?: $totalAmount));

            // Settle all resolved invoices first
            $settledInvoices = collect([]);
            foreach ($invoices as $invoice) {
                $invoice->status = 'Paid';
                $invoice->save();
                $settledInvoices->push($invoice);
            }

            // FIFO Check: If settleAmount is greater than the sum of already settled invoices,
            // or if there are other unpaid overdue invoices and the paid amount covers them,
            // settle all remaining unpaid invoices in chronological order up to settleAmount.
            $alreadySettledSum = (float) $settledInvoices->sum('amount');
            $remainingPayment = $settleAmount - $alreadySettledSum;

            if ($remainingPayment > 0.01 || ($resolved['is_overdue'] ?? false)) {
                $otherUnpaidInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
                    ->whereIn('status', ['Pending', 'Unpaid'])
                    ->whereNotIn('id', $settledInvoices->pluck('id')->toArray())
                    ->orderBy('due_date', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->get();

                foreach ($otherUnpaidInvoices as $otherInv) {
                    if ($remainingPayment >= ((float) $otherInv->amount - 0.05) || (($resolved['is_overdue'] ?? false) && $settleAmount >= (float) $otherInv->amount)) {
                        $otherInv->status = 'Paid';
                        $otherInv->save();
                        $settledInvoices->push($otherInv);
                        $remainingPayment -= (float) $otherInv->amount;
                    }
                }
            }

            // Settle corresponding orders in the week/order_id for all settled invoices
            foreach ($settledInvoices as $sinv) {
                if ($sinv->order_id) {
                    $orderIds = array_filter(array_map('trim', explode(',', $sinv->order_id)));
                    if (!empty($orderIds)) {
                        \App\Models\Order::where('customer_id', $customer->id)
                            ->whereIn('id', $orderIds)
                            ->where('status', 'Payment Pending')
                            ->update(['status' => 'Pending']);
                    }
                }
                if ($sinv->due_date) {
                    $dt = Carbon::parse($sinv->due_date);
                    $wStart = $dt->copy()->startOfWeek()->toDateString();
                    $wEnd = $dt->copy()->endOfWeek()->toDateString();
                    \App\Models\Order::where('customer_id', $customer->id)
                        ->whereBetween('date', [$wStart, $wEnd])
                        ->where('status', 'Payment Pending')
                        ->update(['status' => 'Pending']);
                }
            }

            // Settle any remaining past Payment Pending orders
            if ($resolved['is_overdue'] ?? false) {
                \App\Models\Order::where('customer_id', $customer->id)
                    ->where('status', 'Payment Pending')
                    ->whereDate('date', '<=', Carbon::today()->toDateString())
                    ->update(['status' => 'Pending']);
            }

            $finalPaidAmount = $settleAmount > 0 ? $settleAmount : (float) $settledInvoices->sum('amount');
            $weekCount = $settledInvoices->count();
            $planLabel = $weekCount > 1 
                ? "Overdue Weekly Bills ({$weekCount} weeks)" 
                : ($resolved['label'] ?: 'Weekly Bill Payment');

            \App\Models\Payment::create([
                'id' => 'TXN' . strtoupper(Str::random(8)),
                'customer_id' => $customer->id,
                'customer' => $customer->name,
                'plan' => $planLabel,
                'amount' => $finalPaidAmount,
                'date' => Carbon::now()->toDateString(),
                'status' => 'Successful',
                'payment_intent_id' => $paymentIntentId,
            ]);

            // Reactivate customer account if no other unpaid/pending overdue invoices
            $hasUnpaidOverdue = \App\Models\Invoice::where('customer_id', $customer->id)
                ->whereIn('status', ['Pending', 'Unpaid'])
                ->whereDate('due_date', '<', Carbon::now()->toDateString())
                ->exists();

            if (!$hasUnpaidOverdue) {
                $customer->status = 'Active';
                $customer->save();
            }

            // Admin Database Notification
            \App\Models\Notification::create([
                'title' => 'Weekly Bill Paid',
                'message' => "Customer {$customer->name} has paid AUD " . number_format($finalPaidAmount, 2) . " for {$planLabel}.",
                'user_type' => 'admin',
                'user_id' => null,
                'read_status' => false,
            ]);

            // Admin FCM Push Notification
            FcmService::sendToAdmin(
                'Weekly Bill Paid',
                "Customer {$customer->name} has paid AUD " . number_format($finalPaidAmount, 2) . " for {$planLabel}.",
                ['type' => 'weekly_bill_paid', 'customer_id' => (string)$customer->id, 'amount' => (string)$finalPaidAmount]
            );

            // Customer Database Notification
            \App\Models\Notification::create([
                'title' => 'Weekly Bill Paid',
                'message' => "Your payment of AUD " . number_format($finalPaidAmount, 2) . " for {$planLabel} was successful. Your account is active.",
                'user_type' => 'customer',
                'user_id' => $customer->id,
                'read_status' => false,
            ]);

            // Customer FCM Push Notification
            FcmService::sendToCustomer(
                $customer->id,
                'Weekly Bill Paid',
                "Your weekly payment of AUD " . number_format($finalPaidAmount, 2) . " was successful. Your account is active.",
                ['type' => 'payment_success']
            );

            // Fetch updated remaining balances
            $remainingUnpaid = \App\Models\Invoice::where('customer_id', $customer->id)
                ->whereIn('status', ['Pending', 'Unpaid'])
                ->sum('amount');
            $remainingOverdue = \App\Models\Invoice::where('customer_id', $customer->id)
                ->whereIn('status', ['Pending', 'Unpaid'])
                ->whereDate('due_date', '<', Carbon::now()->toDateString())
                ->sum('amount');

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed successfully. Your account is active and you can continue ordering.',
                'amount_paid' => $finalPaidAmount,
                'outstanding_balance' => round((float) $remainingUnpaid, 2),
                'overdue_balance' => round((float) $remainingOverdue, 2),
                'weekly_balance' => 0.00,
                'account_status' => $customer->status,
                'can_order' => ($customer->status === 'Active'),
                'settled_invoices_count' => $settledInvoices->count(),
                'settled_invoice_ids' => $settledInvoices->pluck('id')->toArray(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment verification failed.',
        ], 400);
    }

    public function createOrderPaymentIntent(Request $request, $orderId)
    {
        $customer = $request->attributes->get('customer') ?: \App\Models\Customer::find($request->input('customer_id'));
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found or unauthorized.'], 401);
        }

        $order = \App\Models\Order::where('customer_id', $customer->id)->find($orderId);
        $resolved = $this->resolveWeeklyInvoicesForPayment($customer, $orderId, $request);
        $totalAmount = (float) $resolved['amount'];
        if ($totalAmount <= 0 && $order) {
            $totalAmount = (float) $order->amount;
        }

        if ($totalAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No outstanding balance due for this order/bill.',
            ], 400);
        }

        $stripeSecret = config('services.stripe.secret') ?: env('STRIPE_SECRET');
        $paymentIntentId = '';
        $clientSecret = '';

        if ($stripeSecret && $stripeSecret !== 'mock') {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $stripeSecret,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])->asForm()->post('https://api.stripe.com/v1/payment_intents', [
                    'amount' => (int)round($totalAmount * 100),
                    'currency' => 'aud',
                    'automatic_payment_methods[enabled]' => 'true',
                ]);

                if ($response->failed()) {
                    $errorData = $response->json();
                    $errorMessage = isset($errorData['error']['message']) ? $errorData['error']['message'] : 'Stripe PaymentIntent creation failed.';
                    return response()->json([
                        'success' => false,
                        'message' => 'Stripe error: ' . $errorMessage
                    ], 400);
                }

                $stripeData = $response->json();
                $paymentIntentId = $stripeData['id'];
                $clientSecret = $stripeData['client_secret'];
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe connection error: ' . $e->getMessage()
                ], 500);
            }
        } else {
            $paymentIntentId = 'pi_mock_' . strtolower(Str::random(16));
            $clientSecret = $paymentIntentId . '_secret_' . strtolower(Str::random(16));
        }

        if ($order) {
            $order->payment_intent_id = $paymentIntentId;
            $order->save();
        }

        $targetBillId = $resolved['invoices']->first() ? $resolved['invoices']->first()->id : ($order ? 'INV-ORD-' . $order->id : 'INV-WEEKLY');

        return response()->json([
            'success' => true,
            'amount' => $totalAmount,
            'stripe_client_secret' => $clientSecret,
            'payment_intent_id' => $paymentIntentId,
            'weekly_bill_id' => $targetBillId,
            'bill_id' => $targetBillId,
            'invoice_id' => $targetBillId,
            'label' => $resolved['label'],
        ]);
    }

    public function confirmOrderPayment(Request $request, $id)
    {
        $customer = $request->attributes->get('customer') ?: \App\Models\Customer::find($request->input('customer_id'));
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found or unauthorized.'], 401);
        }

        $order = \App\Models\Order::where('customer_id', $customer->id)->find($id);
        $paymentIntentId = $request->input('payment_intent_id') ?: ($order ? $order->payment_intent_id : null);

        if (!$paymentIntentId) {
            return response()->json([
                'success' => false,
                'message' => 'Payment intent ID is required.'
            ], 400);
        }

        // Prevent Replay Attacks
        if (\App\Models\Payment::where('payment_intent_id', $paymentIntentId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This payment transaction has already been processed.'
            ], 400);
        }

        // Prevent mock payment intents in production environment
        if (app()->environment('production') && str_starts_with($paymentIntentId, 'pi_mock_')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payment intent.'
            ], 400);
        }

        $resolved = $this->resolveWeeklyInvoicesForPayment($customer, $id, $request);
        $invoices = $resolved['invoices'];
        $totalAmount = (float) $resolved['amount'];
        if ($totalAmount <= 0 && $order) {
            $totalAmount = (float) $order->amount;
        }

        $stripeSecret = config('services.stripe.secret') ?: env('STRIPE_SECRET');
        $paymentCleared = false;
        $actualPaidAmount = 0.0;

        if ($stripeSecret && $stripeSecret !== 'mock' && !str_starts_with($paymentIntentId, 'pi_mock_')) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $stripeSecret,
                ])->get('https://api.stripe.com/v1/payment_intents/' . $paymentIntentId);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['status']) && in_array($data['status'], ['succeeded', 'processing', 'requires_capture'])) {
                        $paymentCleared = true;
                    } elseif (isset($data['status']) && in_array($data['status'], ['requires_payment_method', 'requires_confirmation', 'requires_action']) && app()->environment('local')) {
                        $paymentCleared = true;
                    }
                    if (isset($data['amount'])) {
                        $actualPaidAmount = ((float) $data['amount']) / 100.0;
                    }
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe connection error: ' . $e->getMessage()
                ], 500);
            }
        } else {
            $paymentCleared = true;
        }

        if ($paymentCleared) {
            $settleAmount = $actualPaidAmount > 0 
                ? $actualPaidAmount 
                : ((float) ($request->input('amount') ?: $totalAmount));

            if ($order) {
                $order->status = 'Pending';
                $order->payment_intent_id = $paymentIntentId;
                $order->save();
            }

            // Settle all resolved invoices
            $settledInvoices = collect([]);
            foreach ($invoices as $invoice) {
                $invoice->status = 'Paid';
                $invoice->save();
                $settledInvoices->push($invoice);
            }

            // Settle all corresponding orders in the week for all settled invoices
            foreach ($settledInvoices as $sinv) {
                if ($sinv->order_id) {
                    $orderIds = array_filter(array_map('trim', explode(',', $sinv->order_id)));
                    if (!empty($orderIds)) {
                        \App\Models\Order::where('customer_id', $customer->id)
                            ->whereIn('id', $orderIds)
                            ->where('status', 'Payment Pending')
                            ->update(['status' => 'Pending']);
                    }
                }
                if ($sinv->due_date) {
                    $dt = Carbon::parse($sinv->due_date);
                    $wStart = $dt->copy()->startOfWeek()->toDateString();
                    $wEnd = $dt->copy()->endOfWeek()->toDateString();
                    \App\Models\Order::where('customer_id', $customer->id)
                        ->whereBetween('date', [$wStart, $wEnd])
                        ->where('status', 'Payment Pending')
                        ->update(['status' => 'Pending']);
                }
            }

            // If an order was directly passed, also clear any other order in that same week
            if ($order) {
                $dt = Carbon::parse($order->date);
                $wStart = $dt->copy()->startOfWeek()->toDateString();
                $wEnd = $dt->copy()->endOfWeek()->toDateString();
                \App\Models\Order::where('customer_id', $customer->id)
                    ->whereBetween('date', [$wStart, $wEnd])
                    ->where('status', 'Payment Pending')
                    ->update(['status' => 'Pending']);
            }

            $finalPaidAmount = $settleAmount > 0 ? $settleAmount : ($order ? (float)$order->amount : 0.0);
            $planLabel = $order ? ($order->tiffin . ' (Weekly Settled)') : ($resolved['label'] ?: 'Weekly Bill Payment');

            \App\Models\Payment::create([
                'id' => 'TXN' . strtoupper(Str::random(8)),
                'customer_id' => $customer->id,
                'customer' => $customer->name,
                'plan' => $planLabel,
                'amount' => $finalPaidAmount,
                'date' => Carbon::now()->toDateString(),
                'status' => 'Successful',
                'payment_intent_id' => $paymentIntentId,
            ]);

            // Reactivate account if no other outstanding unpaid/pending previous invoices
            $hasUnpaidOverdue = \App\Models\Invoice::where('customer_id', $customer->id)
                ->whereIn('status', ['Pending', 'Unpaid'])
                ->whereDate('due_date', '<', Carbon::now()->toDateString())
                ->exists();

            if (!$hasUnpaidOverdue) {
                $customer->status = 'Active';
                $customer->save();
            }

            // Admin Database Notification
            \App\Models\Notification::create([
                'title' => 'Weekly Bill Paid',
                'message' => "Customer {$customer->name} paid AUD " . number_format($finalPaidAmount, 2) . " for {$planLabel}.",
                'user_type' => 'admin',
                'user_id' => null,
                'read_status' => false,
            ]);

            // Admin FCM Push Notification
            FcmService::sendToAdmin(
                'Weekly Bill Paid',
                "Customer {$customer->name} paid AUD " . number_format($finalPaidAmount, 2) . " for {$planLabel}.",
                ['type' => 'weekly_bill_paid', 'customer_id' => (string)$customer->id, 'amount' => (string)$finalPaidAmount]
            );

            // Customer Database Notification
            \App\Models\Notification::create([
                'title' => 'Weekly Bill Paid',
                'message' => "Your payment of AUD " . number_format($finalPaidAmount, 2) . " for {$planLabel} was successful. Your account is active.",
                'user_type' => 'customer',
                'user_id' => $customer->id,
                'read_status' => false
            ]);

            // Customer FCM Push Notification
            FcmService::sendToCustomer(
                $customer->id,
                'Weekly Bill Paid',
                "Your weekly payment of AUD " . number_format($finalPaidAmount, 2) . " was successful. Your account is active.",
                ['type' => 'payment_success']
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment verified. Weekly bill and orders confirmed.',
                'order' => $order,
                'amount_paid' => $finalPaidAmount,
                'settled_invoices_count' => $settledInvoices->count(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment verification failed.'
        ], 400);
    }

    /**
     * Dedicated Profile Image Upload for Customer
     */
    public function uploadCustomerProfileImage(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $savedImage = $this->saveUploadedImage(
            $request,
            ['profile_image', 'image', 'avatar', 'photo', 'file'],
            'profile_cust_' . $customer->id,
            'uploads/profiles',
            $customer->profile_image
        );

        if (!$savedImage) {
            return response()->json([
                'success' => false,
                'message' => 'No valid image file or Base64 string was provided.'
            ], 422);
        }

        $customer->update(['profile_image' => $savedImage]);
        $customer->refresh();

        $imageUrl = str_starts_with($customer->profile_image, 'http') ? $customer->profile_image : asset($customer->profile_image);

        return response()->json([
            'success' => true,
            'message' => 'Profile image uploaded successfully.',
            'profile_image' => $imageUrl,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'pincode' => $customer->pincode,
                'address' => $customer->address,
                'profile_image' => $imageUrl,
                'user_type' => $customer->user_type,
                'addresses' => $customer->addresses()->orderBy('is_default', 'desc')->get(),
            ]
        ]);
    }

    /**
     * Dedicated Profile Image Upload for Driver
     */
    public function uploadDriverProfileImage(Request $request)
    {
        $driver = $request->attributes->get('driver');
        $savedImage = ImageUploadHelper::saveProfileImage(
            $request,
            'profile_drv_' . $driver->id,
            $driver->profile_image
        );

        if (!$savedImage) {
            return response()->json([
                'success' => false,
                'message' => 'No valid image file or Base64 string was provided.'
            ], 422);
        }

        $driver->update(['profile_image' => $savedImage]);
        $driver->refresh();

        $imageUrl = str_starts_with($driver->profile_image, 'http') ? $driver->profile_image : asset($driver->profile_image);

        return response()->json([
            'success' => true,
            'message' => 'Profile image uploaded successfully.',
            'profile_image' => $imageUrl,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'first_name' => $driver->first_name,
                'last_name' => $driver->last_name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'address' => $driver->address,
                'license_no' => $driver->license_no,
                'license_expiry' => $driver->license_expiry,
                'license_copy_front' => $driver->license_copy_front ? (str_starts_with($driver->license_copy_front, 'http') ? $driver->license_copy_front : asset($driver->license_copy_front)) : null,
                'license_copy_back' => $driver->license_copy_back ? (str_starts_with($driver->license_copy_back, 'http') ? $driver->license_copy_back : asset($driver->license_copy_back)) : null,
                'profile_image' => $imageUrl,
                'vehicle_reg_no' => $driver->vehicle_reg_no,
                'vehicle_reg_image' => $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null,
                'vehicle_registration_image' => $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null,
                'vehicle_reg_doc' => $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null,
                'assigned_zip' => $driver->assigned_zip,
                'area' => $driver->area,
                'status' => $driver->status,
                'user_type' => $driver->user_type,
            ]
        ]);
    }

    /**
     * Dedicated Vehicle Registration Image Upload for Driver
     */
    public function uploadDriverVehicleImage(Request $request)
    {
        $driver = $request->attributes->get('driver');
        $savedImage = ImageUploadHelper::saveVehicleReg(
            $request,
            'vehicle_reg_drv_' . $driver->id,
            $driver->vehicle_reg_image
        );

        if (!$savedImage) {
            return response()->json([
                'success' => false,
                'message' => 'No valid image file or Base64 string was provided.'
            ], 422);
        }

        $driver->update(['vehicle_reg_image' => $savedImage]);
        $driver->refresh();

        $imageUrl = str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle registration image uploaded successfully.',
            'vehicle_reg_image' => $imageUrl,
            'vehicle_registration_image' => $imageUrl,
            'vehicle_reg_doc' => $imageUrl,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'first_name' => $driver->first_name,
                'last_name' => $driver->last_name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'address' => $driver->address,
                'license_no' => $driver->license_no,
                'license_expiry' => $driver->license_expiry,
                'license_copy_front' => $driver->license_copy_front ? (str_starts_with($driver->license_copy_front, 'http') ? $driver->license_copy_front : asset($driver->license_copy_front)) : null,
                'license_copy_back' => $driver->license_copy_back ? (str_starts_with($driver->license_copy_back, 'http') ? $driver->license_copy_back : asset($driver->license_copy_back)) : null,
                'profile_image' => $driver->profile_image ? (str_starts_with($driver->profile_image, 'http') ? $driver->profile_image : asset($driver->profile_image)) : null,
                'vehicle_reg_no' => $driver->vehicle_reg_no,
                'vehicle_reg_image' => $imageUrl,
                'vehicle_registration_image' => $imageUrl,
                'vehicle_reg_doc' => $imageUrl,
                'assigned_zip' => $driver->assigned_zip,
                'area' => $driver->area,
                'status' => $driver->status,
                'user_type' => $driver->user_type,
            ]
        ]);
    }

    /**
     * Dedicated License Front Copy Upload for Driver
     */
    public function uploadDriverLicenseFront(Request $request)
    {
        $driver = $request->attributes->get('driver');
        $savedImage = ImageUploadHelper::saveLicenseFront(
            $request,
            'license_front_drv_' . $driver->id,
            $driver->license_copy_front
        );

        if (!$savedImage) {
            return response()->json([
                'success' => false,
                'message' => 'No valid license front image file or Base64 string was provided.'
            ], 422);
        }

        $driver->update(['license_copy_front' => $savedImage]);
        $driver->refresh();

        $imageUrl = str_starts_with($driver->license_copy_front, 'http') ? $driver->license_copy_front : asset($driver->license_copy_front);

        return response()->json([
            'success' => true,
            'message' => 'License front image uploaded successfully.',
            'license_copy_front' => $imageUrl,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'first_name' => $driver->first_name,
                'last_name' => $driver->last_name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'address' => $driver->address,
                'license_no' => $driver->license_no,
                'license_expiry' => $driver->license_expiry,
                'license_copy_front' => $imageUrl,
                'license_copy_back' => $driver->license_copy_back ? (str_starts_with($driver->license_copy_back, 'http') ? $driver->license_copy_back : asset($driver->license_copy_back)) : null,
                'profile_image' => $driver->profile_image ? (str_starts_with($driver->profile_image, 'http') ? $driver->profile_image : asset($driver->profile_image)) : null,
                'vehicle_reg_no' => $driver->vehicle_reg_no,
                'vehicle_reg_image' => $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null,
                'vehicle_registration_image' => $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null,
                'vehicle_reg_doc' => $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null,
                'assigned_zip' => $driver->assigned_zip,
                'area' => $driver->area,
                'status' => $driver->status,
                'user_type' => $driver->user_type,
            ]
        ]);
    }

    /**
     * Dedicated License Back Copy Upload for Driver
     */
    public function uploadDriverLicenseBack(Request $request)
    {
        $driver = $request->attributes->get('driver');
        $savedImage = ImageUploadHelper::saveLicenseBack(
            $request,
            'license_back_drv_' . $driver->id,
            $driver->license_copy_back
        );

        if (!$savedImage) {
            return response()->json([
                'success' => false,
                'message' => 'No valid license back image file or Base64 string was provided.'
            ], 422);
        }

        $driver->update(['license_copy_back' => $savedImage]);
        $driver->refresh();

        $imageUrl = str_starts_with($driver->license_copy_back, 'http') ? $driver->license_copy_back : asset($driver->license_copy_back);

        return response()->json([
            'success' => true,
            'message' => 'License back image uploaded successfully.',
            'license_copy_back' => $imageUrl,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'first_name' => $driver->first_name,
                'last_name' => $driver->last_name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'address' => $driver->address,
                'license_no' => $driver->license_no,
                'license_expiry' => $driver->license_expiry,
                'license_copy_front' => $driver->license_copy_front ? (str_starts_with($driver->license_copy_front, 'http') ? $driver->license_copy_front : asset($driver->license_copy_front)) : null,
                'license_copy_back' => $imageUrl,
                'profile_image' => $driver->profile_image ? (str_starts_with($driver->profile_image, 'http') ? $driver->profile_image : asset($driver->profile_image)) : null,
                'vehicle_reg_no' => $driver->vehicle_reg_no,
                'vehicle_reg_image' => $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null,
                'vehicle_registration_image' => $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null,
                'vehicle_reg_doc' => $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null,
                'assigned_zip' => $driver->assigned_zip,
                'area' => $driver->area,
                'status' => $driver->status,
                'user_type' => $driver->user_type,
            ]
        ]);
    }

    /**
     * Dedicated License Upload (Front & Back) for Driver
     */
    public function uploadDriverLicense(Request $request)
    {
        $driver = $request->attributes->get('driver');
        $front = ImageUploadHelper::saveLicenseFront($request, 'license_front_drv_' . $driver->id, $driver->license_copy_front);
        $back = ImageUploadHelper::saveLicenseBack($request, 'license_back_drv_' . $driver->id, $driver->license_copy_back);

        $updates = [];
        if ($front !== null) $updates['license_copy_front'] = $front;
        if ($back !== null) $updates['license_copy_back'] = $back;

        if (!empty($updates)) {
            $driver->update($updates);
            $driver->refresh();
        }

        $licFrontUrl = $driver->license_copy_front ? (str_starts_with($driver->license_copy_front, 'http') ? $driver->license_copy_front : asset($driver->license_copy_front)) : null;
        $licBackUrl = $driver->license_copy_back ? (str_starts_with($driver->license_copy_back, 'http') ? $driver->license_copy_back : asset($driver->license_copy_back)) : null;

        return response()->json([
            'success' => true,
            'message' => 'License documents updated successfully.',
            'license_copy_front' => $licFrontUrl,
            'license_copy_back' => $licBackUrl,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'first_name' => $driver->first_name,
                'last_name' => $driver->last_name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'address' => $driver->address,
                'license_no' => $driver->license_no,
                'license_expiry' => $driver->license_expiry,
                'license_copy_front' => $licFrontUrl,
                'license_copy_back' => $licBackUrl,
                'profile_image' => $driver->profile_image ? (str_starts_with($driver->profile_image, 'http') ? $driver->profile_image : asset($driver->profile_image)) : null,
                'vehicle_reg_no' => $driver->vehicle_reg_no,
                'vehicle_reg_image' => $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null,
                'vehicle_registration_image' => $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null,
                'vehicle_reg_doc' => $driver->vehicle_reg_image ? (str_starts_with($driver->vehicle_reg_image, 'http') ? $driver->vehicle_reg_image : asset($driver->vehicle_reg_image)) : null,
                'assigned_zip' => $driver->assigned_zip,
                'area' => $driver->area,
                'status' => $driver->status,
                'user_type' => $driver->user_type,
            ]
        ]);
    }

    /**
     * Universal image upload & Base64 decoder helper (delegated to ImageUploadHelper)
     */
    protected function saveUploadedImage($request, $fieldNames, $prefix, $uploadFolder, $existingPath = null)
    {
        return ImageUploadHelper::save($request, $fieldNames, $prefix, $uploadFolder, $existingPath);
    }

    /**
     * Update customer FCM device token.
     */
    public function updateCustomerFcmToken(Request $request)
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'FCM token is required.',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer->update(['fcm_token' => $request->fcm_token]);

        return response()->json([
            'success' => true,
            'message' => 'Customer FCM token updated successfully.',
            'fcm_token' => $customer->fcm_token
        ]);
    }

    /**
     * Update driver FCM device token.
     */
    public function updateDriverFcmToken(Request $request)
    {
        $driver = $request->attributes->get('driver');
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'FCM token is required.',
                'errors' => $validator->errors()
            ], 422);
        }

        $driver->update(['fcm_token' => $request->fcm_token]);

        return response()->json([
            'success' => true,
            'message' => 'Driver FCM token updated successfully.',
            'fcm_token' => $driver->fcm_token
        ]);
    }
}

