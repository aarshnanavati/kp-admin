<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'customer_id',
        'order_id',
        'amount',
        'status',
        'due_date',
        'collected_photo',
    ];

    protected $appends = [
        'weekly_bill_id',
        'bill_id',
        'invoice_id',
        'week_id',
        'week_range',
        'start_date',
        'end_date',
    ];

    public function getWeeklyBillIdAttribute()
    {
        if (str_starts_with($this->id, 'INV-W')) {
            return $this->id;
        }
        $dt = \Carbon\Carbon::parse($this->created_at ?: $this->due_date ?: now());
        $wYear = $dt->year;
        $wWeekNum = $dt->weekOfYear;
        return 'INV-W' . $wYear . str_pad((string) $wWeekNum, 2, '0', STR_PAD_LEFT) . '-' . $this->customer_id . '-001';
    }

    public function getBillIdAttribute()
    {
        return $this->weekly_bill_id;
    }

    public function getInvoiceIdAttribute()
    {
        return $this->id;
    }

    public function getWeekIdAttribute()
    {
        $dt = \Carbon\Carbon::parse($this->created_at ?: $this->due_date ?: now());
        return $dt->copy()->startOfWeek()->toDateString() . '_' . $dt->copy()->endOfWeek()->toDateString();
    }

    public function getWeekRangeAttribute()
    {
        $dt = \Carbon\Carbon::parse($this->created_at ?: $this->due_date ?: now());
        return $dt->copy()->startOfWeek()->format('d M Y') . ' - ' . $dt->copy()->endOfWeek()->format('d M Y');
    }

    public function getStartDateAttribute()
    {
        $dt = \Carbon\Carbon::parse($this->created_at ?: $this->due_date ?: now());
        return $dt->copy()->startOfWeek()->toDateString();
    }

    public function getEndDateAttribute()
    {
        $dt = \Carbon\Carbon::parse($this->created_at ?: $this->due_date ?: now());
        return $dt->copy()->endOfWeek()->toDateString();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
