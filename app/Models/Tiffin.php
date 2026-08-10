<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tiffin extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'items',
        'description',
        'prep_time',
        'status',
        'image',
        'category_id',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
