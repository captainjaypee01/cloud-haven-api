<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Image extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'alt_text', 'image_url', 'secure_image_url', 'image_path',
        'provider', 'public_id', 'width', 'height', 'order'
    ];

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'room_image');
    }
}
