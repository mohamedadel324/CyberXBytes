<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
        'users_uuid',
        'event_uuid',
        'team_leader_uuid',
    ];

    public function leader() {
        return $this->belongsTo(User::class, 'team_leader_uuid', 'uuid');
    }
}
