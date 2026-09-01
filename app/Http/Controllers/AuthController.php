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

        $validator = Validator::make($request->all(), [
            'name' => 'required_without:first_name|string|max:255',
            'first_name' => 'required_without:name|string|max:255',
            'last_name' => 'required_without:name|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'password' => 'required|string|min:6|confirmed',
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

        $defaultAddr = $customer->addresses()->create([
            'type' => 'Home',
            'address_line' => trim($request->address),
            'pincode' => trim($request->pincode),
            'is_default' => true,
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
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'pincode' => $customer->pincode,
                'address' => $customer->address,
                'profile_image' => null,
                'addresses' => [$defaultAddr],
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
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'pincode' => $customer->pincode,
                'address' => $customer->address,
                'profile_image' => $customer->profile_image ? asset($customer->profile_image) : null,
                'user_type' => $customer->user_type,
                'addresses' => $customer->addresses()->orderBy('is_default', 'desc')->get(),
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
            $created = $customer->addresses()->create([
                'type' => 'Home',
                'address_line' => $customer->address,
                'pincode' => $customer->pincode,
                'is_default' => true,
            ]);
            $addresses = collect([$created]);
        }

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'pincode' => $customer->pincode,
                'address' => $customer->address,
                'profile_image' => $customer->profile_image ? asset($customer->profile_image) : null,
                'user_type' => $customer->user_type,
                'total_orders' => $customer->orders()->count(),
                'total_spent' => (float)$customer->orders()->sum('amount'),
                'recent_orders' => $customer->orders()->latest()->take(5)->get(),
                'addresses' => $addresses,
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

        $now = Carbon::now();
        // Unpaid outstanding invoices from previous weeks (due date is in the past)
        $outstandingInvoices = \App\Models\Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['Pending', 'Unpaid'])
            ->whereDate('due_date', '<', $now->toDateString())
            ->get();

        $outstandingAmount = $outstandingInvoices->sum('amount');

        if ($outstandingAmount > 0) {
            $earliestDueDate = $outstandingInvoices->min('due_date');
            
            // Count tiffins (quantity) ordered after this earliest due date
            $postSaturdayTiffinsCount = \App\Models\Order::where('customer_id', $customer->id)
                ->where('date', '>', $earliestDueDate)
                ->sum('quantity');

            if ($postSaturdayTiffinsCount >= 2) {
                if ($customer->status !== 'Deactivated') {
                    $customer->status = 'Deactivated';
                    $customer->save();
                }
            }
        }

        if ($customer->status === 'Deactivated') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is deactivated due to unpaid weekly invoices. Please clear your outstanding weekly balance to place new orders.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'tiffin_id' => 'required|exists:tiffins,id',
            'add_ons' => 'nullable|array',
            'note' => 'nullable|string',
            'quantity' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $tiffin = \App\Models\Tiffin::findOrFail($request->tiffin_id);
        $quantity = (int)$request->input('quantity', 1);
        
        $amount = ((float)$tiffin->price * $quantity);
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
            'quantity' => $quantity,
            'area' => $customer->pincode,
            'amount' => $amount,
            'status' => 'Pending',
            'date' => Carbon::now()->toDateString(),
            'add_ons' => json_encode($addonsData),
            'note' => $request->input('note'),
            'payment_intent_id' => '',
        ]);

        // Generate the invoice automatically for this order
        $invoiceId = 'INV-' . Carbon::now()->format('Ymd') . '-' . rand(1000, 9999);
        $invoice = \App\Models\Invoice::create([
            'id' => $invoiceId,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'amount' => $amount,
            'status' => 'Pending',
            'due_date' => Carbon::now()->endOfWeek()->toDateString(),
        ]);

        if ($outstandingAmount > 0) {
            $postSaturdayTiffinsCount = \App\Models\Order::where('customer_id', $customer->id)
                ->where('date', '>', $earliestDueDate)
                ->sum('quantity');

            if ($postSaturdayTiffinsCount >= 2) {
                $customer->status = 'Deactivated';
                $customer->save();
            }
        }

        \App\Models\Notification::create([
            'title' => 'New Order Placed',
            'message' => "Customer {$customer->name} placed order {$order->id} (AUD {$order->amount}). Weekly invoice {$invoiceId} generated.",
            'user_type' => 'admin',
            'user_id' => null,
            'read_status' => false
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully. Weekly invoice generated.',
            'order' => $order,
            'invoice' => $invoice,
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
        $customer = $request->attributes->get('customer');

        $invoices = \App\Models\Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['Pending', 'Unpaid'])
            ->get();

        $totalAmount = $invoices->sum('amount');

        if ($totalAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No outstanding balance due.'
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

        return response()->json([
            'success' => true,
            'amount' => $totalAmount,
            'stripe_client_secret' => $clientSecret,
            'payment_intent_id' => $paymentIntentId,
        ]);
    }

    public function confirmWeeklyBillPayment(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $paymentIntentId = $request->input('payment_intent_id');

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

        $invoices = \App\Models\Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['Pending', 'Unpaid'])
            ->get();

        $totalAmount = $invoices->sum('amount');

        if ($totalAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No outstanding balance to pay.'
            ], 400);
        }

        $stripeSecret = config('services.stripe.secret') ?: env('STRIPE_SECRET');
        $paymentCleared = false;

        if ($stripeSecret && $stripeSecret !== 'mock' && !str_starts_with($paymentIntentId, 'pi_mock_')) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $stripeSecret,
                ])->get('https://api.stripe.com/v1/payment_intents/' . $paymentIntentId);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['status']) && $data['status'] === 'succeeded') {
                        $paymentCleared = true;
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
            $orderIds = $invoices->pluck('order_id')->filter();
            $plans = \App\Models\Order::whereIn('id', $orderIds)->pluck('tiffin')->unique()->toArray();
            $planName = !empty($plans) ? implode(', ', $plans) : 'Weekly Bill Payment';

            foreach ($invoices as $invoice) {
                $invoice->status = 'Paid';
                $invoice->save();
            }

            \App\Models\Payment::create([
                'id' => 'TXN' . strtoupper(Str::random(8)),
                'customer_id' => $customer->id,
                'customer' => $customer->name,
                'plan' => $planName,
                'amount' => $totalAmount,
                'date' => Carbon::now()->toDateString(),
                'status' => 'Successful',
                'payment_intent_id' => $paymentIntentId,
            ]);

            $customer->status = 'Active';
            $customer->save();

            \App\Models\Notification::create([
                'title' => 'Weekly Bill Paid',
                'message' => "Thank you! Your weekly payment of AUD {$totalAmount} was successful.",
                'user_type' => 'customer',
                'user_id' => $customer->id,
                'read_status' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Weekly bill payment confirmed successfully.',
                'amount_paid' => $totalAmount
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment verification failed.'
        ], 400);
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
        $notifications = \App\Models\Notification::where(function ($query) use ($customer) {
                $query->where('user_type', 'customer')
                      ->where('user_id', $customer->id);
            })
            ->orWhere(function ($query) {
                $query->where('user_type', 'customer')
                      ->whereNull('user_id');
            })
            ->orWhere(function ($query) use ($customer) {
                $query->where(function ($q) {
                    $q->whereNull('user_type')->orWhere('user_type', 'admin');
                })->where('message', 'like', "%{$customer->name}%");
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
                'first_name' => $driver->first_name,
                'last_name' => $driver->last_name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'address' => $driver->address,
                'license_no' => $driver->license_no,
                'license_expiry' => $driver->license_expiry,
                'license_copy_front' => $driver->license_copy_front ? asset($driver->license_copy_front) : null,
                'license_copy_back' => $driver->license_copy_back ? asset($driver->license_copy_back) : null,
                'profile_image' => $driver->profile_image ? asset($driver->profile_image) : null,
                'vehicle_reg_no' => $driver->vehicle_reg_no,
                'assigned_zip' => $driver->assigned_zip,
                'area' => $driver->area,
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
            'name' => 'required_without:first_name|string|max:255',
            'first_name' => 'required_without:name|string|max:255',
            'last_name' => 'required_without:name|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255|unique:customers,email,' . $customer->id,
            'pincode' => 'required_without:addresses|string|max:20',
            'address' => 'required_without:addresses|string',
            'old_password' => 'nullable|required_with:password|string',
            'password' => 'nullable|string|min:6|confirmed',
            'profile_image' => 'nullable',
            'image' => 'nullable',
            'avatar' => 'nullable',
            'photo' => 'nullable',
            'addresses' => 'nullable|array',
            'addresses.*.type' => 'required_with:addresses|string|max:50',
            'addresses.*.address_line' => 'required_with:addresses|string',
            'addresses.*.pincode' => 'required_with:addresses|string|max:20',
            'addresses.*.is_default' => 'required_with:addresses|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $data = [
            'name' => trim($request->name),
            'phone' => trim($request->phone),
            'email' => trim($request->email),
        ];

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
                $data['address'] = trim($defaultAddr['address_line']);
                $data['pincode'] = trim($defaultAddr['pincode']);
            }
        } else {
            $data['address'] = trim($request->address);
            $data['pincode'] = trim($request->pincode);
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
                $customer->addresses()->create([
                    'type' => $addr['type'] ?? 'Home',
                    'address_line' => trim($addr['address_line']),
                    'pincode' => trim($addr['pincode']),
                    'is_default' => filter_var($addr['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }

        $profileImageUrl = $customer->profile_image ? (str_starts_with($customer->profile_image, 'http') ? $customer->profile_image : asset($customer->profile_image)) : null;

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'pincode' => $customer->pincode,
                'address' => $customer->address,
                'profile_image' => $profileImageUrl,
                'user_type' => $customer->user_type,
                'addresses' => $customer->addresses()->orderBy('is_default', 'desc')->get(),
            ]
        ]);
    }

    /**
     * Get all saved addresses for customer
     */
    public function getCustomerAddresses(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $addresses = $customer->addresses()->orderBy('is_default', 'desc')->get();
        if ($addresses->isEmpty() && $customer->address) {
            $created = $customer->addresses()->create([
                'type' => 'Home',
                'address_line' => $customer->address,
                'pincode' => $customer->pincode,
                'is_default' => true,
            ]);
            $addresses = collect([$created]);
        }
        return response()->json([
            'success' => true,
            'addresses' => $addresses
        ]);
    }

    /**
     * Add or update a customer address
     */
    public function addOrUpdateCustomerAddress(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|exists:customer_addresses,id',
            'type' => 'required|string|max:50',
            'address_line' => 'required|string',
            'pincode' => 'required|string|max:20',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $isDefault = filter_var($request->input('is_default', false), FILTER_VALIDATE_BOOLEAN);

        if ($isDefault) {
            $customer->addresses()->update(['is_default' => false]);
            $customer->update([
                'address' => trim($request->address_line),
                'pincode' => trim($request->pincode)
            ]);
        }

        if ($request->filled('id')) {
            $address = $customer->addresses()->findOrFail($request->id);
            $address->update([
                'type' => trim($request->type),
                'address_line' => trim($request->address_line),
                'pincode' => trim($request->pincode),
                'is_default' => $isDefault,
            ]);
        } else {
            if ($customer->addresses()->count() === 0) {
                $isDefault = true;
                $customer->update([
                    'address' => trim($request->address_line),
                    'pincode' => trim($request->pincode)
                ]);
            }

            $address = $customer->addresses()->create([
                'type' => trim($request->type),
                'address_line' => trim($request->address_line),
                'pincode' => trim($request->pincode),
                'is_default' => $isDefault,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Address saved successfully.',
            'address' => $address,
            'addresses' => $customer->addresses()->orderBy('is_default', 'desc')->get()
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

        \Illuminate\Support\Facades\Mail::to('admin@kpkitchen.com')->send(new \App\Mail\KitchenAlertMail("Order Cancellation Alert", "Order {$order->id} has been cancelled by customer {$customer->name}."));

        if ($order->driver_id) {
            \App\Models\Notification::create([
                'title' => 'Order Cancelled',
                'message' => "Order {$order->id} has been cancelled by the customer.",
                'user_type' => 'driver',
                'user_id' => $order->driver_id,
                'read_status' => false
            ]);
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
            'name' => 'required_without:first_name|string|max:255',
            'first_name' => 'required_without:name|string|max:255',
            'last_name' => 'required_without:name|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'required|email|max:255|unique:drivers,email,' . $driver->id,
            'address' => 'nullable|string',
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $data = [
            'name' => trim($request->name),
            'phone' => trim($request->phone),
            'email' => strtolower(trim($request->email)),
        ];

        if ($request->has('address')) {
            $data['address'] = $request->address ? trim($request->address) : null;
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
        $savedProfileImage = $this->saveUploadedImage(
            $request,
            ['profile_image', 'image', 'avatar', 'photo', 'file'],
            'profile_drv_' . $driver->id,
            'uploads/profiles',
            $driver->profile_image
        );
        if ($savedProfileImage !== null) {
            $data['profile_image'] = $savedProfileImage;
        }

        // Handle License Front Copy Upload
        $savedLicenseFront = $this->saveUploadedImage(
            $request,
            ['license_copy_front', 'license_front', 'license_front_image'],
            'license_front_drv_' . $driver->id,
            'uploads/licenses',
            $driver->license_copy_front
        );
        if ($savedLicenseFront !== null) {
            $data['license_copy_front'] = $savedLicenseFront;
        }

        // Handle License Back Copy Upload
        $savedLicenseBack = $this->saveUploadedImage(
            $request,
            ['license_copy_back', 'license_back', 'license_back_image'],
            'license_back_drv_' . $driver->id,
            'uploads/licenses',
            $driver->license_copy_back
        );
        if ($savedLicenseBack !== null) {
            $data['license_copy_back'] = $savedLicenseBack;
        }

        $driver->update($data);
        $driver->refresh();

        $profileImageUrl = $driver->profile_image ? (str_starts_with($driver->profile_image, 'http') ? $driver->profile_image : asset($driver->profile_image)) : null;
        $licFrontUrl = $driver->license_copy_front ? (str_starts_with($driver->license_copy_front, 'http') ? $driver->license_copy_front : asset($driver->license_copy_front)) : null;
        $licBackUrl = $driver->license_copy_back ? (str_starts_with($driver->license_copy_back, 'http') ? $driver->license_copy_back : asset($driver->license_copy_back)) : null;

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
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
                'profile_image' => $profileImageUrl,
                'vehicle_reg_no' => $driver->vehicle_reg_no,
                'assigned_zip' => $driver->assigned_zip,
                'area' => $driver->area,
                'status' => $driver->status,
                'user_type' => $driver->user_type,
            ]
        ]);
    }

    public function clearDriverNotifications(Request $request)
    {
        $driver = $request->attributes->get('driver');
        \App\Models\Notification::where('user_type', 'driver')
            ->where('user_id', $driver->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifications cleared successfully.'
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

    public function createBillPaymentIntent(Request $request, $billId)
    {
        $customer = $request->attributes->get('customer');
        $invoice = \App\Models\Invoice::where('customer_id', $customer->id)->findOrFail($billId);

        if ($invoice->status === 'Paid') {
            return response()->json([
                'success' => false,
                'message' => 'This bill has already been paid.'
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
                    'amount' => (int)round($invoice->amount * 100),
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

        return response()->json([
            'success' => true,
            'amount' => $invoice->amount,
            'stripe_client_secret' => $clientSecret,
            'payment_intent_id' => $paymentIntentId,
        ]);
    }

    public function confirmBillPayment(Request $request, $billId)
    {
        $customer = $request->attributes->get('customer');
        $paymentIntentId = $request->input('payment_intent_id');

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

        $invoice = \App\Models\Invoice::where('customer_id', $customer->id)->findOrFail($billId);

        if ($invoice->status === 'Paid') {
            return response()->json([
                'success' => true,
                'message' => 'Bill already marked as paid.',
                'invoice' => $invoice
            ]);
        }

        $stripeSecret = config('services.stripe.secret') ?: env('STRIPE_SECRET');
        $paymentCleared = false;

        if ($stripeSecret && $stripeSecret !== 'mock' && !str_starts_with($paymentIntentId, 'pi_mock_')) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $stripeSecret,
                ])->get('https://api.stripe.com/v1/payment_intents/' . $paymentIntentId);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['status']) && $data['status'] === 'succeeded') {
                        $paymentCleared = true;
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
            $invoice->status = 'Paid';
            $invoice->save();

            // Find order name to save as plan name
            $planName = 'Weekly Bill Payment';
            if ($invoice->order_id) {
                $order = \App\Models\Order::find($invoice->order_id);
                if ($order) {
                    $planName = $order->tiffin;
                }
            }

            \App\Models\Payment::create([
                'id' => 'TXN' . strtoupper(Str::random(8)),
                'customer_id' => $customer->id,
                'customer' => $customer->name,
                'plan' => $planName,
                'amount' => $invoice->amount,
                'date' => Carbon::now()->toDateString(),
                'status' => 'Successful',
                'payment_intent_id' => $paymentIntentId,
            ]);

            // Reactivate customer account if they have no other unpaid/pending previous invoices
            $hasUnpaidOverdue = \App\Models\Invoice::where('customer_id', $customer->id)
                ->whereIn('status', ['Pending', 'Unpaid'])
                ->whereDate('due_date', '<', Carbon::now()->toDateString())
                ->exists();

            if (!$hasUnpaidOverdue) {
                $customer->status = 'Active';
                $customer->save();
            }

            \App\Models\Notification::create([
                'title' => 'Bill Paid',
                'message' => "Your payment of AUD {$invoice->amount} for bill {$invoice->id} was successful.",
                'user_type' => 'customer',
                'user_id' => $customer->id,
                'read_status' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed successfully.',
                'invoice' => $invoice
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment verification failed.'
        ], 400);
    }

    public function createOrderPaymentIntent(Request $request, $orderId)
    {
        $customer = $request->attributes->get('customer');
        $order = \App\Models\Order::where('customer_id', $customer->id)->findOrFail($orderId);

        $stripeSecret = config('services.stripe.secret') ?: env('STRIPE_SECRET');
        $paymentIntentId = '';
        $clientSecret = '';

        if ($stripeSecret && $stripeSecret !== 'mock') {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $stripeSecret,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])->asForm()->post('https://api.stripe.com/v1/payment_intents', [
                    'amount' => (int)round($order->amount * 100),
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

        $order->payment_intent_id = $paymentIntentId;
        $order->save();

        return response()->json([
            'success' => true,
            'amount' => $order->amount,
            'stripe_client_secret' => $clientSecret,
            'payment_intent_id' => $paymentIntentId,
        ]);
    }

    public function confirmOrderPayment(Request $request, $id)
    {
        $customer = $request->attributes->get('customer');
        $order = \App\Models\Order::where('customer_id', $customer->id)->findOrFail($id);
        $paymentIntentId = $request->input('payment_intent_id') ?: $order->payment_intent_id;

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

        $stripeSecret = config('services.stripe.secret') ?: env('STRIPE_SECRET');
        $paymentCleared = false;

        if ($stripeSecret && $stripeSecret !== 'mock' && !str_starts_with($paymentIntentId, 'pi_mock_')) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $stripeSecret,
                ])->get('https://api.stripe.com/v1/payment_intents/' . $paymentIntentId);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['status']) && $data['status'] === 'succeeded') {
                        $paymentCleared = true;
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
            $order->status = 'Pending';
            $order->payment_intent_id = $paymentIntentId;
            $order->save();

            // Settle any matching invoice for this order
            $invoice = \App\Models\Invoice::where('order_id', $order->id)->first();
            if ($invoice) {
                $invoice->status = 'Paid';
                $invoice->save();
            }

            \App\Models\Payment::create([
                'id' => 'TXN' . strtoupper(Str::random(8)),
                'customer_id' => $customer->id,
                'customer' => $customer->name,
                'plan' => $order->tiffin,
                'amount' => $order->amount,
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

            \App\Models\Notification::create([
                'title' => 'Order Paid',
                'message' => "Your payment of AUD {$order->amount} for order {$order->id} was successful.",
                'user_type' => 'customer',
                'user_id' => $customer->id,
                'read_status' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment verified. Order confirmed and sent to kitchen.',
                'order' => $order
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
        $savedImage = $this->saveUploadedImage(
            $request,
            ['profile_image', 'image', 'avatar', 'photo', 'file'],
            'profile_drv_' . $driver->id,
            'uploads/profiles',
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
                'license_copy_front' => $driver->license_copy_front ? asset($driver->license_copy_front) : null,
                'license_copy_back' => $driver->license_copy_back ? asset($driver->license_copy_back) : null,
                'profile_image' => $imageUrl,
                'vehicle_reg_no' => $driver->vehicle_reg_no,
                'assigned_zip' => $driver->assigned_zip,
                'area' => $driver->area,
                'status' => $driver->status,
                'user_type' => $driver->user_type,
            ]
        ]);
    }

    /**
     * Universal image upload & Base64 decoder helper
     */
    protected function saveUploadedImage($request, $fieldNames, $prefix, $uploadFolder, $existingPath = null)
    {
        if (!is_array($fieldNames)) {
            $fieldNames = [$fieldNames];
        }

        $file = null;
        foreach ($fieldNames as $fn) {
            if ($request->hasFile($fn)) {
                $f = $request->file($fn);
                if ($f && $f->isValid()) {
                    $file = $f;
                    break;
                }
            }
        }

        if ($file) {
            if ($existingPath && \Illuminate\Support\Facades\File::exists(public_path($existingPath))) {
                \Illuminate\Support\Facades\File::delete(public_path($existingPath));
            }
            $uploadsDir = public_path($uploadFolder);
            if (!\Illuminate\Support\Facades\File::exists($uploadsDir)) {
                \Illuminate\Support\Facades\File::makeDirectory($uploadsDir, 0777, true, true);
            }
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $fileName = $prefix . '_' . time() . '_' . Str::random(6) . '.' . $ext;
            $file->move($uploadsDir, $fileName);
            return $uploadFolder . '/' . $fileName;
        }

        foreach ($fieldNames as $fn) {
            if ($request->filled($fn) && is_string($request->input($fn))) {
                $str = trim($request->input($fn));
                if (empty($str)) {
                    continue;
                }
                if (str_contains($str, ';base64,')) {
                    $parts = explode(';base64,', $str);
                    $header = $parts[0];
                    $base64Data = $parts[1];
                    $ext = 'jpg';
                    if (str_contains($header, 'png')) $ext = 'png';
                    elseif (str_contains($header, 'webp')) $ext = 'webp';
                    elseif (str_contains($header, 'gif')) $ext = 'gif';

                    $decoded = base64_decode($base64Data);
                    if ($decoded !== false) {
                        if ($existingPath && \Illuminate\Support\Facades\File::exists(public_path($existingPath))) {
                            \Illuminate\Support\Facades\File::delete(public_path($existingPath));
                        }
                        $uploadsDir = public_path($uploadFolder);
                        if (!\Illuminate\Support\Facades\File::exists($uploadsDir)) {
                            \Illuminate\Support\Facades\File::makeDirectory($uploadsDir, 0777, true, true);
                        }
                        $fileName = $prefix . '_' . time() . '_' . Str::random(6) . '.' . $ext;
                        file_put_contents($uploadsDir . '/' . $fileName, $decoded);
                        return $uploadFolder . '/' . $fileName;
                    }
                } elseif (strlen($str) > 100 && base64_decode($str, true) !== false && !str_starts_with($str, 'http') && !str_contains($str, '/')) {
                    $decoded = base64_decode($str, true);
                    if ($decoded !== false) {
                        if ($existingPath && \Illuminate\Support\Facades\File::exists(public_path($existingPath))) {
                            \Illuminate\Support\Facades\File::delete(public_path($existingPath));
                        }
                        $uploadsDir = public_path($uploadFolder);
                        if (!\Illuminate\Support\Facades\File::exists($uploadsDir)) {
                            \Illuminate\Support\Facades\File::makeDirectory($uploadsDir, 0777, true, true);
                        }
                        $fileName = $prefix . '_' . time() . '_' . Str::random(6) . '.jpg';
                        file_put_contents($uploadsDir . '/' . $fileName, $decoded);
                        return $uploadFolder . '/' . $fileName;
                    }
                } elseif (str_starts_with($str, 'uploads/') || str_contains($str, '/uploads/')) {
                    if (str_contains($str, '/uploads/')) {
                        $parts = explode('/uploads/', $str);
                        return 'uploads/' . end($parts);
                    }
                    return $str;
                }
            }
        }

        return null;
    }
}
