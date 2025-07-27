<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MealPrice extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = ['category', 'min_age', 'max_age', 'price'];
    
    protected $appends = ['local_created_at'];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    public function getLocalCreatedAtAttribute()
    {
        $userTimezone = "Asia/Singapore";
        return Carbon::parse($this->created_at)
            ->setTimezone($userTimezone)
            ->format('Y-m-d H:i:s');
    }
}
