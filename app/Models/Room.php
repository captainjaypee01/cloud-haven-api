<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'max_guests', 'extra_guest_fee', 'quantity', 'allows_day_use', 'base_weekend_rate', 'base_weekday_rate' , 'status', 'updated_by', 'created_by'];
}
