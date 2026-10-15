<?php

namespace App\Helpers;

use Carbon\Carbon;

class ApiTokenHelper
{
    /**
     * Column values to store for a newly issued plain-text token.
     * Tokens do not expire (api_token_expires_at is null).
     */
    public static function columns(string $plainToken): array
    {
        return [
            'api_token' => hash('sha256', trim($plainToken)),
            'api_token_expires_at' => null,
        ];
    }

    /**
     * Resolve the model that owns a bearer token, or null when the token is unknown.
     * Tokens do not expire.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    public static function find(string $modelClass, ?string $plainToken)
    {
        if (!$plainToken) {
            return null;
        }

        $plainToken = trim($plainToken);
        if (str_starts_with(strtolower($plainToken), 'bearer ')) {
            $plainToken = trim(substr($plainToken, 7));
        }

        if (!$plainToken) {
            return null;
        }

        $hash = hash('sha256', $plainToken);
        $model = $modelClass::where('api_token', $hash)->first();

        // Tokens issued before hashing was introduced are stored in plain text.
        // Accept them once and upgrade the stored value to a hash. A 64-char
        // value is never a legacy token, so a stolen hash cannot be replayed.
        if (!$model && strlen($plainToken) !== 64) {
            $model = $modelClass::where('api_token', $plainToken)->first();
            if ($model) {
                $model->forceFill(self::columns($plainToken))->save();
            }
        }

        // Tokens do not expire: clear any legacy expiration timestamp if present
        if ($model && $model->api_token_expires_at !== null) {
            try {
                $model->api_token_expires_at = null;
                $model->save();
            } catch (\Throwable $e) {
                // Ignore if read-only or migrating
            }
        }

        return $model;
    }
}
