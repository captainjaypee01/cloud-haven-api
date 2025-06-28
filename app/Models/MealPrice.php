<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MealPrice extends Model
{
    use HasFactory;
    
    protected $fillable = ['category', 'min_age', 'max_age', 'price'];
}
