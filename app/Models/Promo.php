<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promo extends Model
{
    use HasFactory;
    use SoftDeletes; // uncomment if using soft deletes (ensure `deleted_at` in migration)

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'scope',
        'title',
        'description',
        'image_url',
        'expires_at',
        'max_uses',
        'uses_count',
        'exclusive',
        'active'
    ];

    protected $casts = [
        'active'     => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Relationship: Promo can be used in many bookings.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
