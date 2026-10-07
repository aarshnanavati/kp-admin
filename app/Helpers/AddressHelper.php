<?php

namespace App\Helpers;

class AddressHelper
{
    /**
     * Parse address components from a Request or Array.
     * Supports street_address, city/suburb/town, pincode/postcode, or raw address string.
     *
     * @param \Illuminate\Http\Request|array $input
     * @param object|array|null $fallbackModel
     * @return array
     */
    public static function extractAndFormat($input, $fallbackModel = null): array
    {
        $getFromInput = function (array $keys) use ($input) {
            foreach ($keys as $k) {
                if ($input instanceof \Illuminate\Http\Request) {
                    if ($input->filled($k)) {
                        return trim((string)$input->input($k));
                    }
                } elseif (is_array($input)) {
                    if (!empty($input[$k])) {
                        return trim((string)$input[$k]);
                    }
                } elseif (is_object($input) && isset($input->{$k}) && !empty($input->{$k})) {
                    return trim((string)$input->{$k});
                }
            }
            return null;
        };

        $getFromFallback = function (array $keys) use ($fallbackModel) {
            if (!$fallbackModel) return null;
            foreach ($keys as $k) {
                if (is_object($fallbackModel) && isset($fallbackModel->{$k}) && !empty($fallbackModel->{$k})) {
                    return trim((string)$fallbackModel->{$k});
                }
                if (is_array($fallbackModel) && !empty($fallbackModel[$k])) {
                    return trim((string)$fallbackModel[$k]);
                }
            }
            return null;
        };

        $streetKeys = ['street_address', 'street address', 'street', 'address_line', 'address_line_1', 'address_line1', 'address1', 'street_name', 'streetAddress', 'addressLine1'];
        $cityKeys = ['suburbs', 'suburb', 'city', 'town', 'city_suburb', 'area_name', 'suburb_name', 'suburbs_name'];
        $pincodeKeys = ['postcode', 'post code', 'pincode', 'pin code', 'postal_code', 'postalCode', 'zip', 'zipcode', 'assigned_zip', 'area'];
        $rawAddressKeys = ['address', 'delivery_address', 'full_address', 'customer_address'];

        // 1. Extract Street Address
        $street = $getFromInput($streetKeys) ?: $getFromFallback($streetKeys);

        // 2. Extract Suburbs / City / Suburb / Town
        $city = $getFromInput($cityKeys) ?: $getFromFallback($cityKeys);

        // 3. Extract Postcode / Pincode / Zip
        $pincode = $getFromInput($pincodeKeys) ?: $getFromFallback($pincodeKeys);

        // 4. Raw Address fallback if structured fields not fully provided
        $rawAddress = $getFromInput($rawAddressKeys) ?: $getFromFallback($rawAddressKeys);

        if ($rawAddress && (!$street || !$city || !$pincode)) {
            $parsed = self::parseRawAddress($rawAddress);
            if (!$street && !empty($parsed['street_address'])) {
                $street = $parsed['street_address'];
            }
            if (!$city && !empty($parsed['city'])) {
                $city = $parsed['city'];
            }
            if (!$pincode && !empty($parsed['pincode'])) {
                $pincode = $parsed['pincode'];
            }
        }

        // Build 3-line formatted address:
        // Line 1: street address
        // Line 2: city/town/suburbs
        // Line 3: postcode
        $formattedAddress = self::buildFormattedAddress($street, $city, $pincode, $rawAddress);

        return [
            'street_address' => $street,
            'city' => $city,
            'suburb' => $city,
            'suburbs' => $city,
            'town' => $city,
            'city_suburb' => $city,
            'pincode' => $pincode,
            'postcode' => $pincode,
            'postal_code' => $pincode,
            'address' => $formattedAddress,
            'formatted_address' => $formattedAddress,
            'address_line' => $street ?: $formattedAddress,
            'address_details' => [
                'street_address' => $street,
                'city' => $city,
                'suburb' => $city,
                'suburbs' => $city,
                'town' => $city,
                'postcode' => $pincode,
                'pincode' => $pincode,
            ],
        ];
    }

    /**
     * Build standard 3-line format:
     * Line 1: street address
     * Line 2: city/town/suburbs
     * Line 3: postcode
     */
    public static function buildFormattedAddress(?string $street, ?string $city, ?string $pincode, ?string $rawFallback = null): string
    {
        $street = !empty($street) ? trim((string)$street) : null;
        $city = !empty($city) ? trim((string)$city) : null;
        $pincode = !empty($pincode) ? trim((string)$pincode) : null;
        $rawFallback = !empty($rawFallback) ? trim((string)$rawFallback) : null;

        // If street or city is missing, but rawFallback is present and not just a numeric postcode
        if ((empty($street) || empty($city)) && !empty($rawFallback) && !is_numeric($rawFallback)) {
            $parsed = self::parseRawAddress($rawFallback);
            if (empty($street) && !empty($parsed['street_address'])) {
                $street = $parsed['street_address'];
            }
            if (empty($city) && !empty($parsed['city'])) {
                $city = $parsed['city'];
            }
            if (empty($pincode) && !empty($parsed['pincode'])) {
                $pincode = $parsed['pincode'];
            }
        }

        $lines = [];
        if (!empty($street)) {
            $lines[] = $street;
        }
        if (!empty($city)) {
            $lines[] = $city;
        }
        if (!empty($pincode)) {
            $lines[] = $pincode;
        }

        if (!empty($lines)) {
            return implode("\n", $lines);
        }

        if (!empty($rawFallback)) {
            return $rawFallback;
        }

        return '';
    }

    /**
     * Get the full, complete formatted address for a customer across all data sources
     * (structured columns, customer_addresses records, past orders, and raw strings).
     */
    public static function getCustomerFullAddress($customer): string
    {
        if (!$customer) {
            return '';
        }

        $street = !empty($customer->street_address) ? trim((string)$customer->street_address) : null;
        $city = !empty($customer->city) ? trim((string)$customer->city) : null;
        $pincode = !empty($customer->pincode) ? trim((string)$customer->pincode) : null;
        $rawAddress = !empty($customer->address) ? trim((string)$customer->address) : null;

        // 1. Check saved customer addresses relation (is_default or first)
        if ((empty($street) || empty($city)) && method_exists($customer, 'addresses')) {
            $addresses = $customer->relationLoaded('addresses') ? $customer->addresses : $customer->addresses()->get();
            $defaultAddr = $addresses->firstWhere('is_default', 1) ?: $addresses->first();
            if ($defaultAddr) {
                if (empty($street) && !empty($defaultAddr->street_address)) {
                    $street = trim((string)$defaultAddr->street_address);
                }
                if (empty($city) && !empty($defaultAddr->city)) {
                    $city = trim((string)$defaultAddr->city);
                }
                if (empty($pincode) && !empty($defaultAddr->pincode)) {
                    $pincode = trim((string)$defaultAddr->pincode);
                }
                if ((empty($rawAddress) || is_numeric($rawAddress)) && !empty($defaultAddr->address_line)) {
                    $rawAddress = trim((string)$defaultAddr->address_line);
                }
            }
        }

        // 2. Check past orders if rawAddress is still empty or just a numeric zip
        if ((empty($street) || empty($city)) && (empty($rawAddress) || is_numeric($rawAddress))) {
            if (method_exists($customer, 'orders')) {
                $orders = $customer->relationLoaded('orders') ? $customer->orders : $customer->orders()->latest()->get();
                $latestOrder = $orders->first();
                if ($latestOrder) {
                    if (empty($street) && !empty($latestOrder->street_address)) {
                        $street = trim((string)$latestOrder->street_address);
                    }
                    if (empty($city) && !empty($latestOrder->city)) {
                        $city = trim((string)$latestOrder->city);
                    }
                    if (empty($pincode) && !empty($latestOrder->pincode)) {
                        $pincode = trim((string)$latestOrder->pincode);
                    }
                    if (!empty($latestOrder->customer_address) && (empty($rawAddress) || is_numeric($rawAddress))) {
                        $rawAddress = trim((string)$latestOrder->customer_address);
                    }
                }
            }
        }

        // 3. If street or city is empty and rawAddress is non-numeric, parse it
        if ((empty($street) || empty($city)) && !empty($rawAddress) && !is_numeric($rawAddress)) {
            $parsed = self::parseRawAddress($rawAddress);
            if (empty($street) && !empty($parsed['street_address'])) {
                $street = $parsed['street_address'];
            }
            if (empty($city) && !empty($parsed['city'])) {
                $city = $parsed['city'];
            }
            if (empty($pincode) && !empty($parsed['pincode'])) {
                $pincode = $parsed['pincode'];
            }
        }

        return self::buildFormattedAddress($street, $city, $pincode, $rawAddress);
    }

    /**
     * Parse raw address string into street_address, city, and pincode components.
     */
    public static function parseRawAddress(?string $raw): array
    {
        if (empty($raw)) {
            return ['street_address' => null, 'city' => null, 'pincode' => null];
        }

        $raw = trim($raw);

        // Case A: Multiline address (contains \n or \r\n)
        if (str_contains($raw, "\n")) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", "", $raw)))));
            if (count($lines) >= 3) {
                return [
                    'street_address' => $lines[0],
                    'city' => $lines[1],
                    'pincode' => $lines[2],
                ];
            } elseif (count($lines) === 2) {
                // Check if second line has city + postcode (e.g. "Melbourne 3000" or "Melbourne, 3000")
                if (preg_match('/^(.*?)\s*([A-Z]{2,3}\s*)?(\b\d{4,6}\b)$/i', $lines[1], $matches)) {
                    return [
                        'street_address' => $lines[0],
                        'city' => trim($matches[1], ", \t"),
                        'pincode' => trim($matches[3]),
                    ];
                }
                return [
                    'street_address' => $lines[0],
                    'city' => $lines[1],
                    'pincode' => null,
                ];
            } elseif (count($lines) === 1) {
                $raw = $lines[0];
            }
        }

        // Case B: Comma or space separated single line address
        // e.g. "45 Elizabeth Street, Melbourne VIC 3000" or "45 Elizabeth Street, Melbourne, 3000"
        $pincode = null;
        if (preg_match('/\b(\d{4,6})\b/', $raw, $zipMatches)) {
            $pincode = $zipMatches[1];
        }

        // Remove postcode from remainder for street/city extraction
        $withoutZip = $pincode ? preg_replace('/\b' . preg_quote($pincode, '/') . '\b/', '', $raw) : $raw;
        $withoutZip = preg_replace('/\b(VIC|NSW|QLD|WA|SA|TAS|ACT|NT)\b/i', '', $withoutZip); // Remove state abbreviation
        $parts = array_values(array_filter(array_map('trim', explode(',', $withoutZip))));

        $street = null;
        $city = null;

        if (count($parts) >= 2) {
            $street = $parts[0];
            $city = end($parts);
        } elseif (count($parts) === 1) {
            $street = $parts[0];
        }

        return [
            'street_address' => $street ?: $raw,
            'city' => $city,
            'pincode' => $pincode,
        ];
    }

    /**
     * Standard response formatting for Customer or Driver address details.
     */
    public static function formatResponsePayload($model): array
    {
        if (!$model) {
            return [
                'street_address' => null,
                'city' => null,
                'suburb' => null,
                'suburbs' => null,
                'town' => null,
                'city_suburb' => null,
                'pincode' => null,
                'postcode' => null,
                'postal_code' => null,
                'address' => '',
                'formatted_address' => '',
                'address_details' => [
                    'street_address' => null,
                    'city' => null,
                    'suburb' => null,
                    'suburbs' => null,
                    'town' => null,
                    'postcode' => null,
                    'pincode' => null,
                ],
            ];
        }

        if ($model instanceof \App\Models\Customer) {
            $formatted = self::getCustomerFullAddress($model);
            $parsed = self::parseRawAddress($formatted);
            $street = $model->street_address ?: $parsed['street_address'];
            $city = $model->city ?: $parsed['city'];
            $pincode = $model->pincode ?: $parsed['pincode'];
        } else {
            $street = $model->street_address ?? null;
            $city = $model->city ?? null;
            $pincode = $model->pincode ?? ($model->assigned_zip ?? ($model->area ?? null));
            $raw = $model->address ?? ($model->address_line ?? ($model->customer_address ?? null));

            if (!$street || !$city || !$pincode) {
                $parsed = self::parseRawAddress($raw);
                $street = $street ?: $parsed['street_address'];
                $city = $city ?: $parsed['city'];
                $pincode = $pincode ?: $parsed['pincode'];
            }

            $formatted = self::buildFormattedAddress($street, $city, $pincode, $raw);
        }

        return [
            'street_address' => $street,
            'suburbs' => $city,
            'postcode' => $pincode,
            'suburb' => $city,
            'city' => $city,
            'town' => $city,
            'city_suburb' => $city,
            'pincode' => $pincode,
            'postal_code' => $pincode,
            'address' => $formatted,
            'formatted_address' => $formatted,
            'address_details' => [
                'street_address' => $street,
                'suburbs' => $city,
                'postcode' => $pincode,
                'suburb' => $city,
                'city' => $city,
                'town' => $city,
                'pincode' => $pincode,
            ],
        ];
    }

    /**
     * Format a Customer address record for API responses.
     */
    public static function formatAddressRecord($address): array
    {
        if (!$address) {
            return [];
        }

        $street = $address->street_address ?? null;
        $city = $address->city ?? null;
        $pincode = $address->pincode ?? null;
        $raw = $address->address_line ?? ($address->address ?? null);

        if (!$street || !$city || !$pincode) {
            $parsed = self::parseRawAddress($raw);
            $street = $street ?: $parsed['street_address'];
            $city = $city ?: $parsed['city'];
            $pincode = $pincode ?: $parsed['pincode'];
        }

        $formatted = self::buildFormattedAddress($street, $city, $pincode, $raw);

        return [
            'id' => $address->id ?? null,
            'customer_id' => $address->customer_id ?? null,
            'type' => $address->type ?? 'Home',
            'street_address' => $street,
            'suburbs' => $city,
            'postcode' => $pincode,
            'suburb' => $city,
            'city' => $city,
            'town' => $city,
            'pincode' => $pincode,
            'postal_code' => $pincode,
            'address_line' => $formatted,
            'address' => $formatted,
            'formatted_address' => $formatted,
            'is_default' => (bool)($address->is_default ?? false),
            'address_details' => [
                'street_address' => $street,
                'suburbs' => $city,
                'postcode' => $pincode,
                'suburb' => $city,
                'city' => $city,
                'pincode' => $pincode,
            ],
            'created_at' => $address->created_at ?? null,
            'updated_at' => $address->updated_at ?? null,
        ];
    }
}
