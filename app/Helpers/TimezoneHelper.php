<?php

namespace App\Helpers;

use Carbon\Carbon;

class TimezoneHelper
{
    /**
     * Standard Timezone for KP's Kitchen operations (Adelaide, Australia).
     */
    public const ADELAIDE_TIMEZONE = 'Australia/Adelaide';

    /**
     * Cut-off hour for daily tiffin orders in Adelaide time (23:00 / 11:00 PM).
     */
    public const CUTOFF_HOUR = 23;

    /**
     * Cut-off minute for daily tiffin orders.
     */
    public const CUTOFF_MINUTE = 0;

    /**
     * Get current DateTime in Adelaide timezone.
     */
    public static function getAdelaideNow(): Carbon
    {
        return Carbon::now(self::ADELAIDE_TIMEZONE);
    }

    /**
     * Check if the daily order cut-off time (11:00 PM Adelaide time) has passed.
     * After 11:00 PM, customers cannot place orders.
     *
     * @return bool
     */
    public static function isOrderCutoffPassed(): bool
    {
        $adelaideNow = self::getAdelaideNow();
        // Cut-off is strictly 11:00 PM (23:00). If hour >= 23, cutoff has passed.
        return (int)$adelaideNow->hour >= self::CUTOFF_HOUR;
    }

    /**
     * Check if ordering is currently open.
     *
     * @return bool
     */
    public static function canPlaceOrder(): bool
    {
        return !self::isOrderCutoffPassed();
    }

    /**
     * Get detailed ordering status and cutoff metadata for API responses and client UI.
     *
     * @return array
     */
    public static function getOrderingCutoffDetails(): array
    {
        $adelaideNow = self::getAdelaideNow();
        $isCutoff = (int)$adelaideNow->hour >= self::CUTOFF_HOUR;

        return [
            'is_cutoff' => $isCutoff,
            'can_order' => !$isCutoff,
            'cutoff_time' => '11:00 PM',
            'cutoff_hour' => self::CUTOFF_HOUR,
            'timezone' => self::ADELAIDE_TIMEZONE,
            'current_time' => $adelaideNow->format('g:i A'),
            'current_date' => $adelaideNow->toDateString(),
            'current_datetime' => $adelaideNow->toIso8601String(),
            'message' => $isCutoff
                ? 'Orders are closed for today. Kitchen order cut-off time is 11:00 PM Adelaide time. Please place your order tomorrow.'
                : 'Ordering is currently open (Daily cut-off is 11:00 PM Adelaide time).',
        ];
    }
}
