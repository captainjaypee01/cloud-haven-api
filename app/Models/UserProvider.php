<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProvider extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'provider',
        'provider_id',
        'providen_token',
        'provider_refresh_token',
        'created_at',
        'updated_at'
    ];
}
