<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'clerk_id',
        'first_name',
        'last_name',
        'email',
        'role',
        'country_code',
        'contact_number',
        'email_verified_at',
        'password',
        'image',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $appends = ['local_created_at', 'local_updated_at'];

    public function providers(): HasMany
    {
        return $this->hasMany(UserProvider::class);
    }
    
    public function getLocalCreatedAtAttribute()
    {
        $userTimezone = "Asia/Singapore";
        return Carbon::parse($this->created_at)
            ->setTimezone($userTimezone)
            ->format('Y-m-d H:i:s');
    }

    public function getLocalUpdatedAtAttribute()
    {
        $userTimezone = "Asia/Singapore";
        return Carbon::parse($this->updated_at)
            ->setTimezone($userTimezone)
            ->format('Y-m-d H:i:s');
    }
}
