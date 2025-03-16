<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSocialMedia extends Model
{
    protected $fillable = [
        'user_id',
        'discord',
        'instagram',
        'twitter',
        'tiktok',
        'youtube',  
    ];
}
