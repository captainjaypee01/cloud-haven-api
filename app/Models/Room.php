<?php

namespace App\Models;

use App\Enums\RoomStatusEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Room extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = ['name', 'slug', 'short_description', 'description', 'max_guests', 'extra_guest_fee', 'quantity', 'allows_day_use', 'base_weekend_rate', 'base_weekday_rate', 'status', 'updated_by', 'created_by'];

    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn($value) => RoomStatusEnum::from($value)->label(),
            set: fn($value) => is_string($value)
                ? RoomStatusEnum::fromLabel($value)->value
                : $value
        );
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
