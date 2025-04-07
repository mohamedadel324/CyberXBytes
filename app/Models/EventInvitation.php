<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventInvitation extends Model
{
    protected $fillable = [
        'uuid',
        'event_uuid',
        'email',
        'invitation_token',
        'email_sent_at',
        'registered_at',
    ];

    protected $casts = [
        'email_sent_at' => 'datetime',
        'registered_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            if (empty($invitation->uuid)) {
                $invitation->uuid = (string) Str::uuid();
            }
            if (empty($invitation->invitation_token)) {
                $invitation->invitation_token = Str::random(64);
            }
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_uuid', 'uuid');
    }
}
