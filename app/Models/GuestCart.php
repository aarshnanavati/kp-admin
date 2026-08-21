<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuestCart extends Model
{
    use HasFactory;

    protected $table = 'guest_carts';

    protected $fillable = [
        'temp_user_id',
        'tiffin_id',
        'item_id',
        'quantity',
    ];

    public function tiffin()
    {
        return $this->belongsTo(Tiffin::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
