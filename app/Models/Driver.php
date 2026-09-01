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
        'area',
        'status',
        'api_token',
        'user_type',
        'profile_image',
    ];

    protected $hidden = [
        'password',
        'api_token',
    ];

    protected $appends = [
        'first_name',
        'last_name',
    ];

    public function getFirstNameAttribute()
    {
        $parts = explode(' ', $this->name ?? '', 2);
        return $parts[0] ?? '';
    }

    public function getLastNameAttribute()
    {
        $parts = explode(' ', $this->name ?? '', 2);
        return $parts[1] ?? '';
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'driver_id');
    }
}
