<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    /**
     * Send push notification to a single FCM device token.
     *
     * @param string|null $fcmToken
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public static function sendToToken(?string $fcmToken, string $title, string $body, array $data = []): array
    {
        if (empty($fcmToken)) {
            return [
                'success' => false,
                'message' => 'FCM token is empty.'
            ];
        }

        $serverKey = config('services.fcm.server_key') ?: env('FCM_SERVER_KEY');
        $credentialsPath = config('services.fcm.credentials_path') ?: env('FIREBASE_CREDENTIALS');
        $projectId = config('services.fcm.project_id') ?: env('FIREBASE_PROJECT_ID');

        // Cast all data values to string for FCM compatibility
        $stringData = [];
        foreach ($data as $key => $val) {
            $stringData[(string)$key] = is_scalar($val) ? (string)$val : json_encode($val);
        }

        // Method 1: Google Service Account OAuth2 (FCM HTTP v1)
        if (!empty($credentialsPath) && file_exists($credentialsPath) && !empty($projectId)) {
            return static::sendViaHttpV1($credentialsPath, $projectId, $fcmToken, $title, $body, $stringData);
        }

        // Method 2: Legacy Server Key API
        if (!empty($serverKey)) {
            return static::sendViaLegacy($serverKey, $fcmToken, $title, $body, $stringData);
        }

        Log::info("FCM Notification Skipped (FCM credentials not configured): Title='{$title}', Body='{$body}', Token=" . substr($fcmToken, 0, 15) . "...");
        return [
            'success' => false,
            'message' => 'FCM credentials (FCM_SERVER_KEY or FIREBASE_CREDENTIALS) are not configured.'
        ];
    }

    /**
     * Send push notification to a specific Customer by ID.
     */
    public static function sendToCustomer($customerId, string $title, string $body, array $data = []): array
    {
        if (!$customerId) return ['success' => false, 'message' => 'Invalid customer ID.'];

        $customer = $customerId instanceof Customer ? $customerId : Customer::find($customerId);
        if (!$customer || empty($customer->fcm_token)) {
            return ['success' => false, 'message' => 'Customer or FCM token not found.'];
        }

        $data['user_type'] = 'customer';
        $data['customer_id'] = (string)$customer->id;

        return static::sendToToken($customer->fcm_token, $title, $body, $data);
    }

    /**
     * Send push notification to a specific Driver by ID.
     */
    public static function sendToDriver($driverId, string $title, string $body, array $data = []): array
    {
        if (!$driverId) return ['success' => false, 'message' => 'Invalid driver ID.'];

        $driver = $driverId instanceof Driver ? $driverId : Driver::find($driverId);
        if (!$driver || empty($driver->fcm_token)) {
            return ['success' => false, 'message' => 'Driver or FCM token not found.'];
        }

        $data['user_type'] = 'driver';
        $data['driver_id'] = (string)$driver->id;

        return static::sendToToken($driver->fcm_token, $title, $body, $data);
    }

    /**
     * Send push notification to all Admin users.
     */
    public static function sendToAdmin(string $title, string $body, array $data = []): array
    {
        $admins = User::whereNotNull('fcm_token')->where('fcm_token', '!=', '')->get();
        $results = [];

        $data['user_type'] = 'admin';

        foreach ($admins as $admin) {
            $results[$admin->id] = static::sendToToken($admin->fcm_token, $title, $body, $data);
        }

        return [
            'success' => true,
            'count' => count($results),
            'results' => $results
        ];
    }

    /**
     * Send push notification via FCM Legacy API.
     */
    protected static function sendViaLegacy(string $serverKey, string $token, string $title, string $body, array $data): array
    {
        try {
            $payload = [
                'to' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                    'badge' => '1',
                ],
                'data' => $data,
                'priority' => 'high',
            ];

            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post('https://fcm.googleapis.com/fcm/send', $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'response' => $response->json(),
                ];
            }

            Log::warning("FCM Legacy Dispatch Failed: " . $response->body());
            return [
                'success' => false,
                'status' => $response->status(),
                'error' => $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::warning("FCM Legacy Exception: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send push notification via FCM HTTP v1 API using Google Service Account JSON.
     */
    protected static function sendViaHttpV1(string $credentialsPath, string $projectId, string $token, string $title, string $body, array $data): array
    {
        try {
            $accessToken = static::getGoogleAccessToken($credentialsPath);
            if (!$accessToken) {
                return [
                    'success' => false,
                    'message' => 'Failed to generate Google OAuth2 access token.'
                ];
            }

            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default',
                            'channel_id' => 'default_notification_channel',
                        ],
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'badge' => 1,
                            ],
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post($url, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'response' => $response->json(),
                ];
            }

            Log::warning("FCM HTTP v1 Dispatch Failed: " . $response->body());
            return [
                'success' => false,
                'status' => $response->status(),
                'error' => $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::warning("FCM HTTP v1 Exception: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate Google OAuth2 Access Token from Service Account JSON.
     */
    protected static function getGoogleAccessToken(string $credentialsPath): ?string
    {
        try {
            if (!file_exists($credentialsPath)) {
                return null;
            }

            $json = json_decode(file_get_contents($credentialsPath), true);
            if (!isset($json['private_key'], $json['client_email'])) {
                return null;
            }

            $now = time();
            $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claim = base64_encode(json_encode([
                'iss' => $json['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now,
            ]));

            $signatureInput = $header . '.' . $claim;
            $signature = '';

            openssl_sign($signatureInput, $signature, $json['private_key'], 'sha256WithRSAEncryption');
            $jwt = $signatureInput . '.' . base64_encode($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                return $response->json()['access_token'] ?? null;
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning("Google Access Token generation error: " . $e->getMessage());
            return null;
        }
    }
}
