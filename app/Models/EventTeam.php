<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Storage;

class EventTeam extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'event_uuid',
        'name',
        'description',
        'leader_uuid',
        'icon',
        'is_locked'
    ];

    protected $casts = [
        'is_locked' => 'boolean'
    ];

    protected $appends = ['icon_url'];

    public function getIconUrlAttribute()
    {
        return $this->icon ? url('storage/' . $this->icon) : null;
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_uuid', 'uuid');
    }

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_uuid', 'uuid');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'event_team_members', 'team_uuid', 'user_uuid', 'id', 'uuid')
            ->using(EventTeamMember::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function submissions()
    {
        return $this->hasMany(EventChallangeSubmission::class, 'team_uuid', 'id');
    }

    public function joinSecrets()
    {
        return $this->hasMany(EventTeamJoinSecret::class, 'team_uuid', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($team) {
            if ($team->icon) {
                Storage::delete($team->icon);
            }
        });
    }
}
