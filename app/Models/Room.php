<?php

namespace App\Models;

use App\Enums\RoomStatusEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'max_guests', 'extra_guest_fee', 'quantity', 'allows_day_use', 'base_weekend_rate', 'base_weekday_rate' , 'status', 'updated_by', 'created_by'];

    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => RoomStatusEnum::from($value)->label(),
            set: fn ($value) => is_string($value) 
                ? RoomStatusEnum::fromLabel($value)->value 
                : $value
        );
    }
}
