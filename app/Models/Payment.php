<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'customer_id',
        'order_id',
        'payment_intent_id',
        'customer',
        'plan',
        'amount',
        'date',
        'status',
    ];

    public function customerRelation()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
