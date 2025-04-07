<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EventTeamJoinSecret extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'team_uuid',
        'secret',
        'used',
        'used_by_uuid',
        'used_at'
    ];

    protected $casts = [
        'used' => 'boolean',
        'used_at' => 'datetime'
    ];

    public function team()
    {
        return $this->belongsTo(EventTeam::class, 'team_uuid', 'id');
    }

    public function usedBy()
    {
        return $this->belongsTo(User::class, 'used_by_uuid', 'uuid');
    }
}
