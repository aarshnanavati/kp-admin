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
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
