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
        'area',
        'driver_id',
        'driver',
        'amount',
        'status',
        'date',
        'add_ons',
        'proof_of_delivery_photo',
        'proof_of_delivery_signature',
    ];

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
