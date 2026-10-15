<?php

namespace Tests\Feature;

use App\Helpers\ApiTokenHelper;
use App\Mail\SendOtpMail;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tiffin;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end tests for the admin panel, customer API and driver API.
 *
 * Runs against the in-memory SQLite database configured in phpunit.xml, so the
 * real MySQL data is never touched. Mail is captured, push notifications are
 * intercepted, Stripe runs in mock mode (the optional Stripe test-mode check is
 * in StripeTestModeFlowTest), and any file written to public/uploads during a
 * test is deleted afterwards.
 */
class FullApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    /** 1x1 transparent PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private array $uploadsBefore = [];
    /** Fake Stripe PaymentIntents keyed by id. */
    private array $stripeIntents = [];
    private Category $category;
    private Item $roti;
    private Item $dal;
    private Item $gulab;
    private Tiffin $tiffin;
    private Tiffin $customTiffin;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 10:00 in Adelaide: ordering is open (cut-off is 23:00).
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'Australia/Adelaide'));

        Mail::fake();
        // Push notifications are swallowed; Stripe is simulated in memory (no network).
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'googleapis.com')) {
                return Http::response(['name' => 'projects/test/messages/1', 'access_token' => 'test', 'expires_in' => 3600]);
            }
            if (preg_match('~api\.stripe\.com/v1/payment_intents/?(pi_[A-Za-z0-9_]+)?~', $url, $m)) {
                if ($request->method() === 'POST' && empty($m[1])) {
                    $data = $request->data();
                    $meta = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
                    foreach ($data as $k => $v) {
                        if (preg_match('/^metadata\[(\w+)\]$/', (string) $k, $mm)) {
                            $meta[$mm[1]] = $v;
                        }
                    }
                    $id = 'pi_test' . Str::random(14);
                    $this->stripeIntents[$id] = [
                        'id' => $id, 'object' => 'payment_intent', 'client_secret' => $id . '_secret_x',
                        'amount' => (int) $data['amount'], 'amount_received' => (int) $data['amount'],
                        'currency' => 'aud', 'status' => 'succeeded', 'metadata' => $meta,
                    ];
                    return Http::response($this->stripeIntents[$id]);
                }
                if ($request->method() === 'GET' && !empty($m[1])) {
                    return isset($this->stripeIntents[$m[1]])
                        ? Http::response($this->stripeIntents[$m[1]])
                        : Http::response(['error' => ['message' => 'No such payment_intent']], 404);
                }
            }
            return Http::response(['error' => 'unexpected request to ' . $url], 500);
        });
        config(['services.stripe.secret' => 'sk_test_fake_for_tests']);

        $this->uploadsBefore = $this->listUploads();
        $this->seedMenu();
    }

    protected function tearDown(): void
    {
        foreach (array_diff($this->listUploads(), $this->uploadsBefore) as $file) {
            @unlink($file);
        }
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function listUploads(): array
    {
        $dir = public_path('uploads');
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $files[] = $f->getPathname();
        }
        return $files;
    }

    private function seedMenu(): void
    {
        $this->category = Category::create(['name' => 'Mains', 'description' => 'Main dishes']);
        $extras = Category::create(['name' => "Today's Special", 'description' => 'Add-ons']);
        $this->roti = Item::create(['name' => 'Roti', 'price' => 1.00, 'description' => 'Wheat roti', 'status' => 'Active', 'category_id' => $this->category->id]);
        $this->dal = Item::create(['name' => 'Dal', 'price' => 5.00, 'description' => 'Yellow dal', 'status' => 'Active', 'category_id' => $this->category->id]);
        $this->gulab = Item::create(['name' => 'Gulab Jamun', 'price' => 2.50, 'description' => 'Dessert', 'status' => 'Active', 'category_id' => $extras->id]);
        $this->tiffin = Tiffin::create([
            'name' => 'Veg Tiffin', 'price' => 12.00, 'items' => ['Roti', 'Dal', 'Rice'],
            'description' => 'Daily veg tiffin', 'prep_time' => '30', 'status' => 'Active',
            'category_id' => $this->category->id, 'is_customizable' => false,
        ]);
        $this->customTiffin = Tiffin::create([
            'name' => 'Custom tiffin', 'price' => 0.00, 'items' => ['basic' => [], 'addons' => [], 'components' => []],
            'description' => 'Build your own', 'status' => 'Active', 'category_id' => $this->category->id, 'is_customizable' => true,
        ]);
    }

    private function png(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }

    private function dataUri(): string
    {
        return 'data:image/png;base64,' . self::PNG;
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
    }

    private function makeOwner(): array
    {
        $token = Str::random(60);
        $user = User::create([
            'name' => 'Test Owner', 'email' => 'owner@example.test', 'password' => Hash::make('Owner12345'),
            'user_type' => 'admin', 'role' => 'owner',
        ] + ApiTokenHelper::columns($token));
        return [$user, $token];
    }

    private function lastOtpFor(string $email): string
    {
        $otp = null;
        Mail::assertSent(SendOtpMail::class, function (SendOtpMail $mail) use ($email, &$otp) {
            if ($mail->hasTo($email)) {
                $otp = $mail->otp;
            }
            return true;
        });
        $this->assertNotNull($otp, "No OTP email captured for {$email}");
        return $otp;
    }

    /** Full customer sign-up: send OTP, verify, register, log in. Returns [Customer, token]. */
    private function registerCustomer(string $email = 'priya@example.test'): array
    {
        $this->postJson('/api/customer/register/send-otp', ['email' => $email])->assertOk();
        $otp = $this->lastOtpFor($email);

        $this->postJson('/api/customer/register/verify-otp', ['email' => $email, 'otp' => $otp])->assertOk();

        $this->postJson('/api/customer/register', [
            'first_name' => 'Priya', 'last_name' => 'Shah', 'email' => $email, 'phone' => '0412' . random_int(100000, 999999),
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'confirm_password' => 'secret123',
            'street_address' => '10 King William St', 'suburb' => 'Adelaide', 'postcode' => '5000',
            'otp' => $otp,
        ])->assertSuccessful();

        $login = $this->postJson('/api/customer/login', ['email' => $email, 'password' => 'secret123'])->assertOk();
        $token = $login->json('token') ?? $login->json('data.token');
        $this->assertNotEmpty($token, 'Customer login did not return a token');

        return [Customer::where('email', $email)->firstOrFail(), $token];
    }

    /** Driver self-registration with document uploads, then admin approval. Returns [Driver, token]. */
    private function registerApprovedDriver(string $ownerToken, string $email = 'raj@example.test'): array
    {
        $this->post('/api/driver/register', [
            'first_name' => 'Raj', 'last_name' => 'Patel', 'email' => $email, 'phone' => '0498' . random_int(100000, 999999),
            'password' => 'driver123', 'password_confirmation' => 'driver123',
            'license_no' => 'SA123456', 'license_expiry' => '2028-12-31', 'vehicle_reg_no' => 'S123ABC',
            'street_address' => '5 Rundle Mall', 'suburb' => 'Adelaide', 'postcode' => '5000',
            'license_copy_front' => $this->png('front.png'),
            'license_copy_back' => $this->png('back.png'),
            'vehicle_reg_image' => $this->png('rego.png'),
        ], ['Accept' => 'application/json'])->assertSuccessful();

        $driver = Driver::where('email', $email)->firstOrFail();
        $this->assertSame('Pending', $driver->approval_status);
        $this->assertStringStartsWith('uploads/licenses/', (string) $driver->license_copy_front);

        // Pending drivers cannot use the driver API.
        $pendingLogin = $this->postJson('/api/driver/login', ['email' => $email, 'password' => 'driver123']);
        $pendingToken = $pendingLogin->json('token') ?? $pendingLogin->json('data.token');
        if ($pendingToken) {
            $this->getJson('/api/driver/profile', $this->bearer($pendingToken))->assertForbidden();
        }

        $this->postJson("/api/drivers/{$driver->id}/approve", [], $this->bearer($ownerToken))->assertSuccessful();
        $this->assertSame('Approved', $driver->fresh()->approval_status);

        $login = $this->postJson('/api/driver/login', ['email' => $email, 'password' => 'driver123'])->assertOk();
        $token = $login->json('token') ?? $login->json('data.token');
        $this->assertNotEmpty($token);

        return [$driver->fresh(), $token];
    }

    // ------------------------------------------------------------------
    // Customer journey
    // ------------------------------------------------------------------

    public function test_customer_full_journey(): void
    {
        // Public catalogue
        foreach (['/api/customer/tiffins', '/api/customer/tiffin-plans', '/api/tiffin-plans', '/api/customer/customize-tiffin',
                  '/api/customer/categories', '/api/customer/items', '/api/customer/ordering-status', '/api/cutoff-status'] as $url) {
            $this->getJson($url)->assertOk();
        }
        $this->getJson('/api/customer/ordering-status')->assertJsonPath('can_order', true);

        // Guest cart before sign-up
        $guest = 'guest-' . Str::random(24);
        $this->postJson('/api/customer/cart', ['temp_user_id' => $guest, 'tiffin_id' => $this->tiffin->id, 'quantity' => 2])->assertSuccessful();
        $this->getJson('/api/customer/cart?temp_user_id=' . $guest)->assertOk();
        $cartItemId = \App\Models\GuestCart::where('temp_user_id', $guest)->value('id');
        $this->assertNotNull($cartItemId, 'Guest cart item not stored');
        $this->postJson('/api/customer/cart/remove', ['temp_user_id' => $guest, 'cart_item_id' => $cartItemId])->assertSuccessful();
        // Another guest cannot remove it
        $this->postJson('/api/customer/cart', ['temp_user_id' => $guest, 'tiffin_id' => $this->tiffin->id])->assertSuccessful();
        $cartItemId = \App\Models\GuestCart::where('temp_user_id', $guest)->value('id');
        $this->postJson('/api/customer/cart/remove', ['temp_user_id' => 'someone-else-' . Str::random(8), 'cart_item_id' => $cartItemId])->assertNotFound();

        [$customer, $token] = $this->registerCustomer();
        $h = $this->bearer($token);

        // Stored token is a hash, never the plain value
        $this->assertSame(hash('sha256', $token), $customer->fresh()->api_token);

        // Profile
        $this->getJson('/api/customer/profile', $h)->assertOk();
        $this->postJson('/api/customer/profile/edit', ['first_name' => 'Priya', 'last_name' => 'Shah-Mehta', 'phone' => '0412000111'], $h)->assertSuccessful();
        $this->post('/api/customer/profile/image', ['profile_image' => $this->png('me.png')], $h)->assertSuccessful();
        $this->assertStringStartsWith('uploads/profiles/', (string) $customer->fresh()->profile_image);
        $this->postJson('/api/customer/profile-image', ['profile_image' => $this->dataUri()], $h)->assertSuccessful();

        // Addresses: add, update, list, delete
        $this->postJson('/api/customer/addresses', ['type' => 'Work', 'street_address' => '1 North Tce', 'city' => 'Adelaide', 'pincode' => '5000'], $h)->assertSuccessful();
        $addresses = $this->getJson('/api/customer/addresses', $h)->assertOk()->json('addresses') ?? [];
        $work = collect($addresses)->firstWhere('type', 'Work');
        $this->assertNotNull($work, 'New address missing from list');
        $this->postJson('/api/customer/addresses', ['id' => $work['id'], 'type' => 'Work', 'street_address' => '2 North Tce', 'city' => 'Adelaide', 'pincode' => '5000'], $h)->assertSuccessful();
        $this->deleteJson('/api/customer/addresses/' . $work['id'], [], $h)->assertSuccessful();

        // FCM token
        $this->postJson('/api/customer/fcm-token', ['fcm_token' => 'test-fcm-token-customer'], $h)->assertSuccessful();

        // Orders: standard tiffin with add-ons, custom tiffin, pickup
        $order1 = $this->postJson('/api/customer/orders', [
            'tiffin_id' => $this->tiffin->id, 'quantity' => 2, 'note' => 'Less spicy',
            'add_ons' => [['id' => $this->gulab->id, 'qty' => 2]],
        ], $h)->assertSuccessful();
        $this->postJson('/api/customer/orders', [
            'custom_items' => [['item_id' => $this->roti->id, 'qty' => 4], ['item_id' => $this->dal->id, 'qty' => 1]],
        ], $h)->assertSuccessful();
        $this->postJson('/api/customer/orders', ['tiffin_id' => $this->tiffin->id, 'order_type' => 'pickup'], $h)->assertSuccessful();

        $orders = Order::where('customer_id', $customer->id)->orderBy('created_at')->get();
        $this->assertCount(3, $orders);

        // Prices come from the server, not the client: 2 x 12.00 + 2 x 2.50 add-ons (+ any delivery fee)
        $first = $orders->firstWhere('tiffin_id', $this->tiffin->id);
        $this->assertGreaterThanOrEqual(29.00, (float) $first->amount);
        // Custom tiffin: 4 x Roti 1.00 + Dal 5.00 = 9.00 (+ any delivery fee)
        $custom = $orders->firstWhere('tiffin_id', $this->customTiffin->id);
        $this->assertNotNull($custom, 'Custom tiffin order not created');
        $this->assertGreaterThanOrEqual(9.00, (float) $custom->amount);

        // Client cannot set its own price
        $this->postJson('/api/customer/orders', ['tiffin_id' => $this->tiffin->id, 'amount' => 0.01, 'price' => 0.01], $h)->assertSuccessful();
        $this->assertGreaterThanOrEqual(12.00, (float) Order::where('customer_id', $customer->id)->latest('created_at')->first()->amount);

        // Invalid quantity rejected
        $this->postJson('/api/customer/orders', ['tiffin_id' => $this->tiffin->id, 'quantity' => -3], $h)->assertStatus(422);

        $this->getJson('/api/customer/orders', $h)->assertOk();

        // Cancel one order
        $this->postJson('/api/customer/orders/' . $orders->last()->id . '/cancel', [], $h)->assertSuccessful();
        $this->assertSame('Cancelled', $orders->last()->fresh()->status);

        // Bills and payments (mock Stripe mode)
        $this->getJson('/api/customer/weekly-payments/current', $h)->assertOk();
        foreach (['/api/customer/invoices', '/api/customer/weekly-bills', '/api/customer/weekly-billing', '/api/customer/weekly-payments'] as $url) {
            $this->getJson($url, $h)->assertOk();
        }

        $intent = $this->postJson('/api/customer/orders/' . $first->id . '/create-payment-intent', [], $h)->assertSuccessful();
        $piId = $intent->json('payment_intent_id');
        $this->assertArrayHasKey($piId, $this->stripeIntents, 'Payment intent not created with Stripe');
        $this->assertSame((string) $customer->id, (string) ($this->stripeIntents[$piId]['metadata']['customer_id'] ?? ''), 'Intent not tagged with the customer');
        $this->assertSame('order', $this->stripeIntents[$piId]['metadata']['kind'] ?? null);

        // A payment that is still processing does not settle anything
        $this->stripeIntents[$piId]['status'] = 'processing';
        $this->postJson('/api/customer/orders/' . $first->id . '/confirm', ['payment_intent_id' => $piId], $h);
        $this->assertFalse(Payment::where('payment_intent_id', $piId)->exists(), 'Processing payment was recorded as paid');
        $this->stripeIntents[$piId]['status'] = 'succeeded';

        // Underpayment is refused
        $this->stripeIntents[$piId]['amount_received'] = 100;
        $this->postJson('/api/customer/orders/' . $first->id . '/confirm', ['payment_intent_id' => $piId], $h)->assertStatus(400);
        $this->stripeIntents[$piId]['amount_received'] = $this->stripeIntents[$piId]['amount'];

        // Mock ids are refused while Stripe is configured
        $this->postJson('/api/customer/orders/' . $first->id . '/confirm', ['payment_intent_id' => 'pi_mock_abc'], $h)->assertStatus(400);

        // The real payment settles; a client-sent amount is ignored
        $this->postJson('/api/customer/orders/' . $first->id . '/confirm', ['payment_intent_id' => $piId, 'amount' => 0.01], $h)->assertSuccessful();
        $this->assertTrue(Payment::where('payment_intent_id', $piId)->exists(), 'Payment not recorded');

        // Replaying the same payment is refused
        $this->postJson('/api/customer/orders/' . $first->id . '/confirm', ['payment_intent_id' => $piId], $h)->assertStatus(400);

        $bills = $this->getJson('/api/customer/weekly-bills', $h)->assertOk()->json();
        $this->assertIsArray($bills);

        // Notifications
        $this->getJson('/api/customer/notifications', $h)->assertOk();
        $this->postJson('/api/customer/notifications/clear-all', [], $h)->assertSuccessful();

        // Logout revokes the token
        $this->postJson('/api/customer/logout', [], $h)->assertSuccessful();
        $this->getJson('/api/customer/profile', $h)->assertUnauthorized();
    }

    public function test_customer_weekly_bill_payment_flow(): void
    {
        [$customer, $token] = $this->registerCustomer('weekly@example.test');
        $h = $this->bearer($token);

        $this->postJson('/api/customer/orders', ['tiffin_id' => $this->tiffin->id, 'quantity' => 1], $h)->assertSuccessful();
        $this->postJson('/api/customer/orders', ['tiffin_id' => $this->tiffin->id, 'quantity' => 1], $h)->assertSuccessful();

        $current = $this->getJson('/api/customer/weekly-payments/current', $h)->assertOk();
        $billId = $current->json('data.bill_id') ?? $current->json('bill_id') ?? $current->json('data.id') ?? 'current';

        $pay = $this->postJson('/api/customer/pay-weekly-bill', ['bill_id' => $billId], $h);
        $this->assertTrue(in_array($pay->status(), [200, 400], true), 'pay-weekly-bill returned ' . $pay->status() . ': ' . $pay->getContent());
        if ($pay->status() === 200) {
            $piId = $pay->json('payment_intent_id');
            $this->postJson('/api/customer/pay-weekly-bill/confirm', ['payment_intent_id' => $piId, 'bill_id' => $pay->json('bill_id')], $h)->assertSuccessful();
            $this->assertTrue(Payment::where('payment_intent_id', $piId)->exists());
        }

        // Bill-level intents
        $invoice = Invoice::where('customer_id', $customer->id)->first();
        if ($invoice) {
            $this->getJson('/api/customer/weekly-bills/' . $invoice->id, $h)->assertOk();
            $this->getJson('/api/customer/invoices/' . $invoice->id, $h)->assertOk();
            $intent = $this->postJson('/api/customer/weekly-bills/' . $invoice->id . '/create-payment-intent', [], $h);
            $this->assertTrue(in_array($intent->status(), [200, 400], true), $intent->getContent());
            if ($intent->status() === 200) {
                $this->postJson('/api/customer/weekly-bills/' . $invoice->id . '/confirm-payment', ['payment_intent_id' => $intent->json('payment_intent_id')], $h)->assertSuccessful();
            }
        }
    }

    public function test_stripe_payment_ownership_and_webhook_settlement(): void
    {
        [$alice, $aliceToken] = $this->registerCustomer('alice@example.test');
        [$bob, $bobToken] = $this->registerCustomer('bob@example.test');
        $this->postJson('/api/customer/orders', ['tiffin_id' => $this->tiffin->id], $this->bearer($aliceToken))->assertSuccessful();
        $this->postJson('/api/customer/orders', ['tiffin_id' => $this->tiffin->id], $this->bearer($bobToken))->assertSuccessful();
        $aliceOrder = Order::where('customer_id', $alice->id)->firstOrFail();
        $bobOrder = Order::where('customer_id', $bob->id)->firstOrFail();

        // Bob cannot settle his order with Alice's payment
        $aliceIntent = $this->postJson('/api/customer/orders/' . $aliceOrder->id . '/create-payment-intent', [], $this->bearer($aliceToken))->assertSuccessful()->json('payment_intent_id');
        $this->postJson('/api/customer/orders/' . $bobOrder->id . '/confirm', ['payment_intent_id' => $aliceIntent], $this->bearer($bobToken))->assertStatus(403);
        $this->assertFalse(Payment::where('payment_intent_id', $aliceIntent)->exists());

        // Alice closes the app before confirming: the signed webhook settles it
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        $payload = json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => $this->stripeIntents[$aliceIntent]]]);
        $t = time();
        $sig = 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $payload, 'whsec_test');
        $server = ['HTTP_STRIPE_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        $this->call('POST', '/api/stripe/webhook', [], [], [], $server, $payload)->assertOk();
        $this->assertTrue(Payment::where('payment_intent_id', $aliceIntent)->exists(), 'Webhook did not record the payment');

        // Delivering the same event again changes nothing, and the app's own confirm sees it as done
        $this->call('POST', '/api/stripe/webhook', [], [], [], $server, $payload)->assertOk();
        $this->assertSame(1, Payment::where('payment_intent_id', $aliceIntent)->count());
        $this->postJson('/api/customer/orders/' . $aliceOrder->id . '/confirm', ['payment_intent_id' => $aliceIntent], $this->bearer($aliceToken))->assertStatus(400);
    }

    public function test_customer_password_reset(): void
    {
        $this->registerCustomer('reset@example.test');

        $this->postJson('/api/customer/forgot-password', ['email' => 'reset@example.test'])->assertOk();
        $otp = $this->lastOtpFor('reset@example.test');

        $this->postJson('/api/customer/verify-otp', ['email' => 'reset@example.test', 'otp' => $otp])->assertOk();
        $this->postJson('/api/customer/reset-password', [
            'email' => 'reset@example.test', 'otp' => $otp, 'password' => 'newpass123', 'confirm_password' => 'newpass123',
        ])->assertOk();

        $this->postJson('/api/customer/login', ['email' => 'reset@example.test', 'password' => 'newpass123'])->assertOk();
        $this->postJson('/api/customer/login', ['email' => 'reset@example.test', 'password' => 'secret123'])->assertStatus(401);

        // Unknown email gets the same reply (no account discovery)
        $this->postJson('/api/customer/forgot-password', ['email' => 'nobody@example.test'])->assertOk()->assertJsonPath('success', true);
    }

    public function test_customer_cannot_touch_another_customers_data(): void
    {
        [, $tokenA] = $this->registerCustomer('a@example.test');
        [, $tokenB] = $this->registerCustomer('b@example.test');

        $this->postJson('/api/customer/orders', ['tiffin_id' => $this->tiffin->id], $this->bearer($tokenA))->assertSuccessful();
        $orderA = Order::latest('created_at')->first();

        $this->postJson('/api/customer/orders/' . $orderA->id . '/cancel', [], $this->bearer($tokenB))->assertNotFound();
        $this->assertNotSame('Cancelled', $orderA->fresh()->status);

        // Passing another customer's id in the body is ignored
        $res = $this->getJson('/api/customer/orders?customer_id=' . $orderA->customer_id, $this->bearer($tokenB))->assertOk();
        $this->assertStringNotContainsString((string) $orderA->id, $res->getContent());
    }

    // ------------------------------------------------------------------
    // Driver journey
    // ------------------------------------------------------------------

    public function test_driver_full_journey_with_proof_of_delivery(): void
    {
        [$owner, $ownerToken] = $this->makeOwner();
        [$customer, $customerToken] = $this->registerCustomer('deliver@example.test');
        [$driver, $token] = $this->registerApprovedDriver($ownerToken);
        $h = $this->bearer($token);

        // Profile and document links are signed, private URLs
        $profile = $this->getJson('/api/driver/profile', $h)->assertOk();
        $front = $profile->json('data.license_copy_front') ?? $profile->json('driver.license_copy_front') ?? data_get($profile->json(), 'data.driver.license_copy_front');
        $this->assertNotNull($front, 'Licence URL missing from profile: ' . $profile->getContent());
        $this->assertStringContainsString('/secure-media/licenses/', $front);
        $this->assertStringContainsString('signature=', $front);

        $this->postJson('/api/driver/profile/edit', ['first_name' => 'Raj', 'last_name' => 'Patel', 'phone' => '0498111222'], $h)->assertSuccessful();
        $this->post('/api/driver/profile/image', ['profile_image' => $this->png('driver.png')], $h)->assertSuccessful();
        $this->post('/api/driver/vehicle-image', ['vehicle_reg_image' => $this->png('rego2.png')], $h)->assertSuccessful();
        $this->post('/api/driver/license-front', ['license_front' => $this->png('lf.png')], $h)->assertSuccessful();
        $this->post('/api/driver/license-back', ['license_back' => $this->png('lb.png')], $h)->assertSuccessful();
        $this->postJson('/api/driver/license', ['license_front' => $this->dataUri(), 'license_back' => $this->dataUri()], $h)->assertSuccessful();
        $this->postJson('/api/driver/fcm-token', ['fcm_token' => 'test-fcm-token-driver'], $h)->assertSuccessful();

        // Customer orders, admin assigns the driver
        $this->postJson('/api/customer/orders', ['tiffin_id' => $this->tiffin->id], $this->bearer($customerToken))->assertSuccessful();
        $this->postJson('/api/customer/orders', ['tiffin_id' => $this->tiffin->id], $this->bearer($customerToken))->assertSuccessful();
        [$order, $order2] = Order::where('customer_id', $customer->id)->orderBy('created_at')->get()->all();
        Order::whereIn('id', [$order->id, $order2->id])->update(['driver_id' => $driver->id, 'driver' => $driver->name]);

        $this->getJson('/api/driver/assigned-orders', $h)->assertOk()->assertSee((string) $order->id);
        $this->getJson('/api/driver/orders', $h)->assertOk();
        $this->getJson('/api/driver/orders/' . $order->id, $h)->assertOk();

        $this->patchJson('/api/driver/orders/' . $order->id . '/status', ['status' => 'Out for Delivery'], $h)->assertSuccessful();

        // Proof of delivery: photo file + signature as base64
        $this->post('/api/driver/orders/' . $order->id . '/upload-pod', [
            'proof_photo' => $this->png('pod.png'),
            'proof_signature' => $this->dataUri(),
        ], $h)->assertSuccessful();
        $order->refresh();
        $this->assertStringStartsWith('uploads/pod/', (string) $order->proof_of_delivery_photo);
        $this->assertStringEndsWith('.png', (string) $order->proof_of_delivery_photo);
        $this->assertFileExists(public_path($order->proof_of_delivery_photo));
        $this->assertNotEmpty($order->proof_of_delivery_signature);

        // POD via the body-id route and base64 photo
        $this->postJson('/api/driver/upload-pod', ['order_id' => $order2->id, 'proof_photo' => $this->dataUri()], $h)->assertSuccessful();
        $this->assertNotEmpty($order2->fresh()->proof_of_delivery_photo);

        // Status update with photo, then delivered
        $this->post('/api/driver/orders/' . $order2->id . '/status', ['status' => 'Delivered', 'photo' => $this->png('drop.png')], $h)->assertSuccessful();
        $this->assertSame('Delivered', $order2->fresh()->status);

        // A PHP file disguised as an image is rejected
        // Real temp file (Laravel's fake files report their type from the name, not the content)
        $tmp = tempnam(storage_path('framework/testing'), 'shell');
        file_put_contents($tmp, "<?php echo 'x'; ?>");
        $shell = new UploadedFile($tmp, 'pod.jpg', null, null, true);
        $this->post('/api/driver/orders/' . $order->id . '/upload-pod', ['proof_photo' => $shell], $h)->assertStatus(422);

        // Notifications and logout
        $this->getJson('/api/driver/notifications', $h)->assertOk();
        $this->postJson('/api/driver/notifications/clear-all', [], $h)->assertSuccessful();
        $this->postJson('/api/driver/logout', [], $h)->assertSuccessful();
        $this->getJson('/api/driver/profile', $h)->assertUnauthorized();
    }

    public function test_driver_cannot_upload_pod_for_another_drivers_order(): void
    {
        [, $ownerToken] = $this->makeOwner();
        [$customer, $customerToken] = $this->registerCustomer('pod@example.test');
        [$driverA] = $this->registerApprovedDriver($ownerToken, 'drivera@example.test');
        [, $tokenB] = $this->registerApprovedDriver($ownerToken, 'driverb@example.test');

        $this->postJson('/api/customer/orders', ['tiffin_id' => $this->tiffin->id], $this->bearer($customerToken))->assertSuccessful();
        $order = Order::where('customer_id', $customer->id)->first();
        $order->update(['driver_id' => $driverA->id, 'driver' => $driverA->name]);

        $this->post('/api/driver/orders/' . $order->id . '/upload-pod', ['proof_photo' => $this->png()], $this->bearer($tokenB))->assertNotFound();
        $this->getJson('/api/driver/orders/' . $order->id, $this->bearer($tokenB))->assertNotFound();

        // No login at all
        $this->post('/api/driver/orders/' . $order->id . '/upload-pod', ['proof_photo' => $this->png(), 'driver_id' => $driverA->id], ['Accept' => 'application/json'])->assertUnauthorized();
        $this->assertNull($order->fresh()->proof_of_delivery_photo);
    }

    public function test_driver_password_reset_and_rejection(): void
    {
        [, $ownerToken] = $this->makeOwner();
        $this->post('/api/driver/register', [
            'first_name' => 'Sam', 'last_name' => 'Lee', 'email' => 'sam@example.test', 'phone' => '0400111999',
            'password' => 'driver123', 'password_confirmation' => 'driver123', 'license_no' => 'SA999', 'vehicle_reg_no' => 'S999',
            'street_address' => '1 Test St', 'suburb' => 'Adelaide', 'postcode' => '5000',
            'license_copy_front' => $this->png(), 'license_copy_back' => $this->png(),
        ], ['Accept' => 'application/json'])->assertSuccessful();
        $driver = Driver::where('email', 'sam@example.test')->firstOrFail();

        $this->postJson("/api/drivers/{$driver->id}/reject", ['reason' => 'Licence unreadable'], $this->bearer($ownerToken))->assertSuccessful();
        $this->assertSame('Rejected', $driver->fresh()->approval_status);

        $this->postJson('/api/driver/forgot-password', ['email' => 'sam@example.test'])->assertOk();
        $otp = $this->lastOtpFor('sam@example.test');
        $this->postJson('/api/driver/verify-otp', ['email' => 'sam@example.test', 'otp' => $otp])->assertOk();
        $this->postJson('/api/driver/reset-password', ['email' => 'sam@example.test', 'otp' => $otp, 'password' => 'newdriver1', 'confirm_password' => 'newdriver1'])->assertOk();
        $this->assertTrue(Hash::check('newdriver1', $driver->fresh()->password));
    }

    // ------------------------------------------------------------------
    // Admin
    // ------------------------------------------------------------------

    public function test_admin_api_login_and_profile(): void
    {
        $this->makeOwner();

        $this->postJson('/api/auth/login', ['email' => 'owner@example.test', 'password' => 'wrong'])->assertUnauthorized();
        $login = $this->postJson('/api/login', ['email' => 'owner@example.test', 'password' => 'Owner12345'])->assertOk();
        $token = $login->json('token');
        $h = $this->bearer($token);

        $this->getJson('/api/admin/profile', $h)->assertOk();
        $this->postJson('/api/admin/profile/edit', ['name' => 'Kitchen Owner', 'email' => 'owner@example.test'], $h)->assertSuccessful();
        $this->postJson('/api/admin/profile/edit', ['name' => 'Kitchen Owner', 'email' => 'owner@example.test', 'password' => 'weak'], $h)->assertStatus(422);
        $this->postJson('/api/admin/fcm-token', ['fcm_token' => 'admin-fcm'], $h)->assertSuccessful();
        $this->postJson('/api/fcm-token', ['fcm_token' => 'admin-fcm-2'], $h)->assertSuccessful();
        $this->postJson('/api/admin/notifications/clear-all', [], $h)->assertSuccessful();

        // Admin password reset
        $this->postJson('/api/auth/forgot-password', ['email' => 'owner@example.test'])->assertOk();
        $otp = $this->lastOtpFor('owner@example.test');
        $this->postJson('/api/auth/verify-otp', ['email' => 'owner@example.test', 'otp' => $otp])->assertOk();
        $this->postJson('/api/auth/reset-password', ['email' => 'owner@example.test', 'otp' => $otp, 'password' => 'short', 'confirm_password' => 'short'])->assertStatus(200)->assertJsonPath('success', false);
        $this->postJson('/api/auth/reset-password', ['email' => 'owner@example.test', 'otp' => $otp, 'password' => 'Newpass123', 'confirm_password' => 'Newpass123'])->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/logout', [], $h)->assertOk();
        $this->getJson('/api/admin/profile', $h)->assertUnauthorized();

        // No public admin sign-up
        $this->postJson('/api/register', ['name' => 'x', 'email' => 'x@example.test', 'password' => 'abc12345', 'confirm_password' => 'abc12345'])->assertNotFound();
    }

    public function test_admin_manages_menu_customers_drivers_coupons(): void
    {
        [, $token] = $this->makeOwner();
        $h = $this->bearer($token);

        // Categories
        $this->postJson('/api/categories', ['action' => 'create', 'name' => 'Desserts', 'description' => 'Sweet'], $h)->assertSuccessful();
        $cat = Category::where('name', 'Desserts')->firstOrFail();
        $this->postJson('/api/categories', ['action' => 'update', 'id' => $cat->id, 'name' => 'Sweets', 'description' => 'Sweet'], $h)->assertSuccessful();
        $this->getJson('/api/categories', $h)->assertOk()->assertSee('Sweets');

        // Items, with image upload
        $this->post('/api/items', ['action' => 'create', 'name' => 'Kheer', 'price' => 4.5, 'category_id' => $cat->id, 'status' => 'Active', 'image_file' => $this->png('kheer.png')], $h)->assertSuccessful();
        $item = Item::where('name', 'Kheer')->firstOrFail();
        $this->postJson('/api/items', ['action' => 'update', 'id' => $item->id, 'name' => 'Rice Kheer', 'price' => 5, 'category_id' => $cat->id, 'status' => 'Active', 'image' => $this->dataUri()], $h)->assertSuccessful();
        $this->postJson('/api/items', ['action' => 'update', 'id' => $item->id, 'name' => 'Rice Kheer', 'price' => 5, 'category_id' => $cat->id, 'status' => 'Inactive'], $h)->assertSuccessful();
        $this->assertSame('Inactive', $item->fresh()->status);
        $this->getJson('/api/items', $h)->assertOk();

        // Tiffins
        $this->post('/api/tiffins', ['action' => 'create', 'name' => 'Deluxe Tiffin', 'price' => 18, 'items' => json_encode(['Roti', 'Paneer']), 'category_id' => $this->category->id, 'status' => 'Active', 'prep_time' => '30', 'image_file' => $this->png('t.png')], $h)->assertSuccessful();
        $tiffin = Tiffin::where('name', 'Deluxe Tiffin')->firstOrFail();
        $this->postJson('/api/tiffins', ['action' => 'update', 'id' => $tiffin->id, 'name' => 'Deluxe Tiffin', 'price' => 19, 'items' => ['Roti', 'Paneer'], 'category_id' => $this->category->id, 'status' => 'Active'], $h)->assertSuccessful();
        $this->getJson('/api/tiffins', $h)->assertOk();
        $this->getJson('/api/customize-tiffin', $h)->assertOk();
        $this->postJson('/api/tiffins', ['action' => 'delete', 'id' => $tiffin->id], $h)->assertSuccessful();
        $this->postJson('/api/items', ['action' => 'delete', 'id' => $item->id], $h)->assertSuccessful();
        $this->postJson('/api/categories', ['action' => 'delete', 'id' => $cat->id], $h)->assertSuccessful();

        // Creating records without an optional status must not crash
        foreach ([
            ['/api/items', ['action' => 'create', 'name' => 'No Status Item', 'price' => 3, 'category_id' => $this->category->id]],
            ['/api/tiffins', ['action' => 'create', 'name' => 'No Status Tiffin', 'price' => 10, 'items' => ['Roti'], 'category_id' => $this->category->id]],
            // Unknown category must be a validation error, not a crash
            ['/api/items', ['action' => 'create', 'name' => 'Orphan', 'price' => 3, 'category_id' => 999999]],
            ['/api/coupons', ['action' => 'create', 'code' => 'NOSTATUS', 'type' => 'Flat', 'value' => 5, 'expiry_date' => '2026-12-31']],
            ['/api/drivers', ['action' => 'create', 'name' => 'No Status Driver', 'email' => 'nostatus@example.test', 'phone' => '0400999888', 'password' => 'driver123']],
        ] as [$url, $payload]) {
            $res = $this->postJson($url, $payload, $h);
            $this->assertLessThan(500, $res->status(), "{$url} create without status crashed: " . substr((string) $res->getContent(), 0, 200));
        }

        // Coupons
        $this->postJson('/api/coupons', ['action' => 'create', 'code' => 'WELCOME10', 'type' => 'Percentage', 'value' => 10, 'expiry_date' => '2026-12-31', 'status' => 'Active'], $h)->assertSuccessful();
        $this->getJson('/api/coupons', $h)->assertOk()->assertSee('WELCOME10');

        // Customers
        $this->postJson('/api/customers', ['action' => 'create', 'name' => 'Walk In', 'email' => 'walkin@example.test', 'phone' => '0400222333', 'password' => 'walkin123', 'address' => '3 Test St', 'pincode' => '5000'], $h)->assertSuccessful();
        $customer = Customer::where('email', 'walkin@example.test')->firstOrFail();
        $this->getJson('/api/customers', $h)->assertOk();
        $this->getJson('/api/customers/' . $customer->id . '/details', $h)->assertOk();
        $this->postJson('/api/customers/' . $customer->id . '/status', ['status' => 'Deactivated'], $h)->assertSuccessful();
        $this->assertSame('Deactivated', $customer->fresh()->status);
        $this->postJson('/api/customers/' . $customer->id . '/toggle-status', [], $h)->assertSuccessful();

        // Drivers (admin-created)
        $this->post('/api/drivers', ['action' => 'create', 'name' => 'Admin Made', 'email' => 'made@example.test', 'phone' => '0400333444', 'password' => 'driver123', 'license_no' => 'SA1', 'vehicle_reg_no' => 'S1', 'assigned_zip' => '5000', 'license_copy_front_file' => $this->png()], $h)->assertSuccessful();
        $made = Driver::where('email', 'made@example.test')->firstOrFail();
        $this->getJson('/api/drivers', $h)->assertOk();
        $this->getJson('/api/drivers/' . $made->id . '/details', $h)->assertOk();
        $this->postJson('/api/drivers', ['action' => 'update', 'id' => $made->id, 'name' => 'Admin Made', 'email' => 'made@example.test', 'phone' => '0400333444', 'status' => 'Active'], $h)->assertSuccessful();
        $this->postJson('/api/drivers', ['action' => 'delete', 'id' => $made->id], $h)->assertSuccessful();
    }

    public function test_admin_orders_dispatch_pod_invoices_payments_reports(): void
    {
        [$owner, $token] = $this->makeOwner();
        $h = $this->bearer($token);
        [$customer, $customerToken] = $this->registerCustomer('ops@example.test');
        [$driver] = $this->registerApprovedDriver($token, 'opsdriver@example.test');

        $this->postJson('/api/customer/orders', ['tiffin_id' => $this->tiffin->id, 'quantity' => 2, 'add_ons' => [['name' => 'Gulab Jamun', 'qty' => 1]]], $this->bearer($customerToken))->assertSuccessful();
        $order = Order::where('customer_id', $customer->id)->firstOrFail();

        $this->getJson('/api/orders', $h)->assertOk();
        $this->getJson('/api/orders/' . $order->id . '/details', $h)->assertOk();
        $this->postJson('/api/orders/update', ['id' => $order->id, 'status' => 'Preparing', 'driver' => $driver->name], $h)->assertSuccessful();
        $this->assertSame($driver->id, (int) $order->fresh()->driver_id);
        $this->postJson('/api/orders/dispatch', ['order_ids' => [$order->id]], $h)->assertSuccessful();
        $this->assertSame('Out for Delivery', $order->fresh()->status);

        // Admin uploads proof of delivery
        $this->post('/api/orders/' . $order->id . '/upload-pod', ['proof_photo' => $this->png('adminpod.png')], $h)->assertSuccessful();
        $this->assertStringStartsWith('uploads/pod/', (string) $order->fresh()->proof_of_delivery_photo);

        // Invoices
        $this->postJson('/api/invoices', ['action' => 'create', 'customer_id' => $customer->id, 'amount' => 24.5, 'status' => 'Pending', 'due_date' => '2026-10-20'], $h)->assertSuccessful();
        $invoice = Invoice::where('customer_id', $customer->id)->latest('created_at')->firstOrFail();
        $this->getJson('/api/invoices', $h)->assertOk();
        $this->getJson('/api/invoices/' . $invoice->id, $h)->assertOk();
        $this->postJson('/api/invoices', ['action' => 'update_status', 'id' => $invoice->id, 'status' => 'Paid'], $h)->assertSuccessful();
        $this->postJson('/api/invoices/weekly', ['action' => 'generate_weekly'], $h)->assertSuccessful();

        // Payments and notifications
        $this->getJson('/api/payments', $h)->assertOk();
        $this->getJson('/api/notifications', $h)->assertOk();
        $this->postJson('/api/notifications/read', ['action' => 'mark_all_read'], $h)->assertSuccessful();

        // Dashboard and reports
        $this->getJson('/api/data', $h)->assertOk();
        $this->getJson('/api/dashboard-charts', $h)->assertOk();
        $this->getJson('/api/reports/kitchen-prep?date=2026-10-14', $h)->assertOk();
        foreach (['sales', 'kitchen', 'drivers', 'pickup'] as $type) {
            $res = $this->get('/api/reports/export?type=' . $type . '&start_date=2026-10-01&end_date=2026-10-31', $h);
            $this->assertLessThan(500, $res->getStatusCode(), "Export {$type} failed: " . substr((string) $res->getContent(), 0, 300));
        }
        foreach (['sales', 'kitchen_prep', 'detailed_orders'] as $type) {
            $res = $this->get('/api/reports/export-pdf?type=' . $type . '&start_date=2026-10-01&end_date=2026-10-31', $h);
            $this->assertLessThan(500, $res->getStatusCode(), "PDF {$type} failed: " . substr((string) $res->getContent(), 0, 300));
        }
    }

    public function test_admin_roles(): void
    {
        [$owner, $ownerToken] = $this->makeOwner();
        $managerToken = Str::random(60);
        User::create(['name' => 'Manager', 'email' => 'manager@example.test', 'password' => Hash::make('Manager123'), 'user_type' => 'admin', 'role' => 'manager'] + ApiTokenHelper::columns($managerToken));

        $this->postJson('/api/users', ['action' => 'create', 'name' => 'X', 'email' => 'x@example.test', 'password' => 'abcd1234'], $this->bearer($managerToken))->assertForbidden();
        $this->getJson('/api/customers', $this->bearer($managerToken))->assertOk();

        $this->postJson('/api/users', ['action' => 'create', 'name' => 'New', 'email' => 'new@example.test', 'password' => 'weak'], $this->bearer($ownerToken))->assertStatus(422);
        $this->postJson('/api/users', ['action' => 'create', 'name' => 'New', 'email' => 'new@example.test', 'password' => 'abcd1234'], $this->bearer($ownerToken))->assertOk()->assertJsonPath('user.role', 'manager');
        $this->getJson('/api/users', $this->bearer($ownerToken))->assertOk()->assertSee('new@example.test');
        $this->postJson('/api/users', ['action' => 'delete', 'id' => $owner->id], $this->bearer($ownerToken))->assertStatus(400);
        $this->postJson('/api/users', ['action' => 'update', 'id' => $owner->id, 'name' => 'Owner', 'email' => 'owner@example.test', 'role' => 'manager'], $this->bearer($ownerToken))->assertStatus(400);
    }

    public function test_admin_web_pages_and_forms(): void
    {
        [$owner] = $this->makeOwner();

        // Login form
        $this->get('/login')->assertOk();
        $this->get('/register')->assertNotFound();
        $this->post('/login', ['email' => 'owner@example.test', 'password' => 'Owner12345'])->assertRedirect();
        $this->assertAuthenticatedAs($owner);

        foreach (['/', '/drivers', '/tiffins', '/orders', '/payments', '/notifications', '/customers', '/menu/categories', '/menu/items', '/coupons', '/invoices', '/users', '/reports'] as $page) {
            $res = $this->get($page);
            $this->assertSame(200, $res->status(), "Page {$page} returned " . $res->status());
        }

        // Web form posts (CSRF is not enforced by the test client, auth and roles are)
        $csrf = session()->token();
        $this->post('/categories/save', ['_token' => $csrf, 'name' => 'Web Cat', 'description' => 'x'])->assertRedirect();
        $this->assertDatabaseHas('categories', ['name' => 'Web Cat']);
        $this->post('/users/save', ['_token' => $csrf, 'name' => 'Web Admin', 'email' => 'webadmin@example.test', 'password' => 'webpass123', 'role' => 'manager'])->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'webadmin@example.test', 'role' => 'manager']);

        // Session-authenticated API calls must carry the CSRF token
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->postJson('/api/notifications/read', ['action' => 'mark_all_read'])->assertStatus(419);
        $this->withHeader('X-CSRF-TOKEN', session()->token())->postJson('/api/notifications/read', ['action' => 'mark_all_read'])->assertSuccessful();

        // Private documents open for a logged-in admin
        $file = 'test_doc_' . Str::random(6) . '.png';
        file_put_contents(public_path('uploads/licenses/' . $file), base64_decode(self::PNG));
        $this->get('/secure-media/licenses/' . $file)->assertOk();
        auth()->logout();
        $this->get('/secure-media/licenses/' . $file)->assertForbidden();

        $this->post('/logout', ['_token' => $csrf])->assertRedirect();
    }

    public function test_security_headers_and_public_errors(): void
    {
        $res = $this->get('/login');
        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNotNull($res->headers->get('Content-Security-Policy'));

        $this->postJson('/api/stripe/webhook', [])->assertStatus(503);
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        $this->postJson('/api/stripe/webhook', ['type' => 'payment_intent.succeeded'], ['Stripe-Signature' => 't=' . time() . ',v1=bad'])->assertStatus(400);
    }

    // ------------------------------------------------------------------
    // Every registered route: nothing may crash (500) or leak without auth
    // ------------------------------------------------------------------

    public function test_every_route_responds_without_server_errors(): void
    {
        [, $ownerToken] = $this->makeOwner();
        [$customer, $customerToken] = $this->registerCustomer('sweep@example.test');
        [$driver, $driverToken] = $this->registerApprovedDriver($ownerToken, 'sweepdriver@example.test');
        $this->postJson('/api/customer/orders', ['tiffin_id' => $this->tiffin->id], $this->bearer($customerToken))->assertSuccessful();
        $order = Order::first();
        $order->update(['driver_id' => $driver->id, 'driver' => $driver->name]);
        $invoice = Invoice::create(['id' => 'INV-SWEEP-1', 'customer_id' => $customer->id, 'amount' => 10, 'status' => 'Pending', 'due_date' => '2026-10-20']);

        $params = [
            'id' => $order->id, 'orderId' => $order->id, 'billId' => $invoice->id,
            'folder' => 'licenses', 'file' => 'missing.png',
        ];
        $skip = ['logout', 'up', 'storage/{path}', 'api/stripe/webhook']; // webhook: 503 without a secret is expected (covered elsewhere)
        $failures = [];
        $unprotected = [];
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (in_array($uri, $skip, true) || str_ends_with($uri, '/logout') || str_starts_with($uri, '_')) {
                continue;
            }
            $middleware = implode(',', $route->gatherMiddleware());
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $path = '/' . preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($params, $uri) {
                    if ($m[1] === 'id' && str_contains($uri, 'customers/')) return (string) $GLOBALS['__sweep_customer'];
                    if ($m[1] === 'id' && str_contains($uri, 'drivers/')) return (string) $GLOBALS['__sweep_driver'];
                    if ($m[1] === 'id' && str_contains($uri, 'invoices')) return 'INV-SWEEP-1';
                    if ($m[1] === 'id' && str_contains($uri, 'addresses')) return '999999';
                    return (string) ($params[$m[1]] ?? '1');
                }, $uri);
                $GLOBALS['__sweep_customer'] = $customer->id;
                $GLOBALS['__sweep_driver'] = $driver->id;

                $headers = ['Accept' => 'application/json'];
                if (str_contains($middleware, 'customer.auth')) {
                    $headers['Authorization'] = 'Bearer ' . $customerToken;
                } elseif (str_contains($middleware, 'driver.auth')) {
                    $headers['Authorization'] = 'Bearer ' . $driverToken;
                } elseif (str_contains($middleware, 'api.or.session')) {
                    $headers['Authorization'] = 'Bearer ' . $ownerToken;
                    // Without auth these must be refused
                    $this->app['auth']->forgetGuards();
                    $anon = $this->call($method, $path, [], [], [], ['HTTP_ACCEPT' => 'application/json']);
                    if (!in_array($anon->getStatusCode(), [401, 302, 405], true)) {
                        $unprotected[] = "{$method} {$path} -> {$anon->getStatusCode()} without login";
                    }
                }

                $server = [];
                foreach ($headers as $k => $v) {
                    $server['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
                }
                $this->app['auth']->forgetGuards();
                $res = $this->call($method, $path, [], [], [], $server);
                $this->app['auth']->forgetGuards();
                $checked++;
                if ($res->getStatusCode() >= 500) {
                    $body = json_decode((string) $res->getContent(), true);
                    $failures[] = "{$method} {$path} -> {$res->getStatusCode()} " . substr((string) ($body['message'] ?? $res->getContent()), 0, 160);
                }
            }
        }

        fwrite(STDERR, "\n[route sweep] {$checked} route/method combinations called\n");
        $this->assertSame([], $unprotected, "Admin routes reachable without login:\n" . implode("\n", $unprotected));
        $this->assertSame([], $failures, "Routes returning server errors:\n" . implode("\n", $failures));
    }
}
