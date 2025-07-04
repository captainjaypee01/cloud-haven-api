<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'provider',
        'status',
        'amount',
        'error_code',
        'error_message',
        'transaction_id',
        'response_data'
    ];
}
