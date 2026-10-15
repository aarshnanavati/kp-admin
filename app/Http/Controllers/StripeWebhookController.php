<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    /**
     * Seconds a webhook signature timestamp may differ from now.
     */
    private const TOLERANCE = 300;

    /**
     * Settle payments Stripe reports as succeeded, so a bill is marked paid even
     * when the app closes before calling the confirm endpoint. The confirm
     * endpoints still work as before; whichever runs first records the payment
     * and the other sees it as already processed.
     */
    public function handle(Request $request)
    {
        $secret = config('services.stripe.webhook_secret');
        if (!$secret) {
            Log::warning('Stripe webhook received but STRIPE_WEBHOOK_SECRET is not set.');
            return response()->json(['message' => 'Webhook not configured.'], 503);
        }

        $payload = $request->getContent();
        if (!$this->hasValidSignature($payload, (string) $request->header('Stripe-Signature'), $secret)) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $event = json_decode($payload, true);
        if (($event['type'] ?? null) !== 'payment_intent.succeeded') {
            return response()->json(['received' => true]);
        }

        $intent = $event['data']['object'] ?? [];
        $intentId = $intent['id'] ?? null;
        $meta = $intent['metadata'] ?? [];

        if (!$intentId || Payment::where('payment_intent_id', $intentId)->exists()) {
            return response()->json(['received' => true]);
        }

        $customer = isset($meta['customer_id']) ? Customer::find($meta['customer_id']) : null;
        if (!$customer) {
            Log::warning("Stripe webhook: payment {$intentId} has no known customer; settle it manually.");
            return response()->json(['received' => true]);
        }

        // Re-use the normal confirm flow, which re-checks the payment with Stripe.
        $confirmRequest = Request::create('/', 'POST', array_filter([
            'payment_intent_id' => $intentId,
            'bill_id' => $meta['bill_id'] ?? null,
        ]));
        $confirmRequest->attributes->set('customer', $customer);

        $auth = app(AuthController::class);
        $kind = $meta['kind'] ?? 'bill';

        if ($kind === 'order' && !empty($meta['order_id'])) {
            $response = $auth->confirmOrderPayment($confirmRequest, $meta['order_id']);
        } elseif ($kind === 'weekly') {
            $response = $auth->confirmWeeklyBillPayment($confirmRequest);
        } else {
            $response = $auth->confirmBillPayment($confirmRequest, $meta['bill_id'] ?? null);
        }

        $result = method_exists($response, 'getData') ? $response->getData(true) : [];
        if (empty($result['success'])) {
            Log::warning("Stripe webhook: payment {$intentId} could not be settled automatically: " . ($result['message'] ?? 'unknown reason'));
        }

        return response()->json(['received' => true]);
    }

    /**
     * Verify the Stripe-Signature header (t=timestamp,v1=hmac).
     */
    private function hasValidSignature(string $payload, string $header, string $secret): bool
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value) {
                $signatures[] = $value;
            }
        }

        if (!$timestamp || !$signatures || abs(time() - $timestamp) > self::TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
