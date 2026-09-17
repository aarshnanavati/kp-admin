<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'customer_id',
        'customer',
        'tiffin_id',
        'tiffin',
        'quantity',
        'area',
        'driver_id',
        'driver',
        'amount',
        'status',
        'date',
        'add_ons',
        'selections',
        'proof_of_delivery_photo',
        'proof_of_delivery_signature',
        'note',
        'payment_intent_id',
    ];

    protected $casts = [
        'selections' => 'array',
    ];

    protected $appends = [
        'weekly_bill_id',
        'bill_id',
        'week_id',
        'week_range',
    ];

    public function getWeeklyBillIdAttribute()
    {
        $dt = \Carbon\Carbon::parse($this->date ?: $this->created_at ?: now());
        $wYear = $dt->year;
        $wWeekNum = $dt->weekOfYear;
        return 'INV-W' . $wYear . str_pad((string) $wWeekNum, 2, '0', STR_PAD_LEFT) . '-' . $this->customer_id . '-001';
    }

    public function getBillIdAttribute()
    {
        return $this->weekly_bill_id;
    }

    public function getWeekIdAttribute()
    {
        $dt = \Carbon\Carbon::parse($this->date ?: $this->created_at ?: now());
        return $dt->copy()->startOfWeek()->toDateString() . '_' . $dt->copy()->endOfWeek()->toDateString();
    }

    public function getWeekRangeAttribute()
    {
        $dt = \Carbon\Carbon::parse($this->date ?: $this->created_at ?: now());
        return $dt->copy()->startOfWeek()->format('d M Y') . ' - ' . $dt->copy()->endOfWeek()->format('d M Y');
    }

    public function customerRelation()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function driverRelation()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function tiffinRelation()
    {
        return $this->belongsTo(Tiffin::class, 'tiffin_id');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class, 'order_id', 'id');
    }
}
