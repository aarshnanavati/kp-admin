<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class PasswordOtp extends Model
{
    use HasFactory;

    /**
     * Marker stored in place of a code once an email has been verified.
     */
    public const VERIFIED = 'VERIFIED';

    protected $fillable = [
        'email',
        'otp',
        'expires_at',
    ];

    protected $hidden = [
        'otp',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Replace any existing codes for the email with a new one. Only a hash is
     * stored; the plain code is returned so it can be emailed.
     */
    public static function issue(string $email, int $minutes = 10): string
    {
        $otp = (string) random_int(100000, 999999);

        static::where('email', $email)->delete();
        static::create([
            'email' => $email,
            'otp' => Hash::make($otp),
            'expires_at' => now()->addMinutes($minutes),
        ]);

        return $otp;
    }

    /**
     * Find an unexpired record for the email whose code matches. When
     * $allowVerified is true, an already-verified record also counts.
     */
    public static function findValid(string $email, $otp, bool $allowVerified = false): ?self
    {
        $otp = trim((string) $otp);

        $records = static::where('email', $email)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->get();

        foreach ($records as $record) {
            if ($record->otp === self::VERIFIED) {
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
     * Find an unexpired record marking the email as already verified.
     */
    public static function findVerified(string $email): ?self
    {
        return static::where('email', $email)
            ->where('otp', self::VERIFIED)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Fallback query scope if Eloquent routes static calls to query builder.
     */
    public function scopeIssue($query, string $email, int $minutes = 10): string
    {
        return static::issue($email, $minutes);
    }
}
