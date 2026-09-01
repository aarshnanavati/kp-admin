<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'pincode',
        'address',
        'password',
        'api_token',
        'user_type',
        'login_count',
        'profile_image',
        'status',
    ];

    protected $appends = [
        'first_name',
        'last_name',
    ];

    public function getFirstNameAttribute()
    {
        $parts = explode(' ', $this->name, 2);
        return $parts[0] ?? '';
    }

    public function getLastNameAttribute()
    {
        $parts = explode(' ', $this->name, 2);
        return $parts[1] ?? '';
    }

    protected $hidden = [
        'password',
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
