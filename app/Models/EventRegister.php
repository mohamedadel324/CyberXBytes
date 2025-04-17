<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegister extends Model
{
    protected $fillable = [
        'event_uuid',
        'user_uuid',
    ];

    
}
