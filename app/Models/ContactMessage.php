<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'message',
        'ip_address',
        'user_agent',
        'is_spam',
        'spam_reason',
        'submitted_at',
    ];

    protected $casts = [
        'is_spam' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    /**
     * Scope to get non-spam messages
     */
    public function scopeNotSpam($query)
    {
        return $query->where('is_spam', false);
    }

    /**
     * Scope to get spam messages
     */
    public function scopeSpam($query)
    {
        return $query->where('is_spam', true);
    }

    /**
     * Scope to get recent messages by email
     */
    public function scopeRecentByEmail($query, $email, $hours = 24)
    {
        return $query->where('email', $email)
                    ->where('submitted_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope to get recent messages by IP
     */
    public function scopeRecentByIp($query, $ip, $hours = 1)
    {
        return $query->where('ip_address', $ip)
                    ->where('submitted_at', '>=', now()->subHours($hours));
    }
}
