<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'address',
        'license_no',
        'license_copy_front',
        'license_copy_back',
        'license_expiry',
        'vehicle_reg_no',
        'assigned_zip',
        'status',
        'api_token',
    ];

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'driver_id');
    }
}
