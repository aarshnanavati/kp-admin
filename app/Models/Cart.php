<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'temp_user_id',
        'tiffin_id',
        'item_id',
        'quantity',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function tiffin()
    {
        return $this->belongsTo(Tiffin::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
