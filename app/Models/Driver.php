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
        'approval_status',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by',
        'api_token',
        'user_type',
        'profile_image',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'api_token',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function isApproved(): bool
    {
        return $this->approval_status === 'Approved';
    }

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
