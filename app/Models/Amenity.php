<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Amenity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'description', 'icon', 'price', 'status'];

    public function rooms()
    {
        return $this->belongsToMany(Room::class);
    }
}
