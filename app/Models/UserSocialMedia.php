<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class UserSocialMedia extends Model
{
    protected $fillable = [
        'user_uuid',
        'discord',
        'instagram',
        'twitter',
        'tiktok',
        'youtube',  
        'linkedIn'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'user_uuid',
        'created_at',
        'updated_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid');
    }
}
