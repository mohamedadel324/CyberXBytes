<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\EventRegistration;
use App\Mail\EventRegistrationMail;
use Illuminate\Support\Facades\Mail;

class EventInvitation extends Model
{
    protected $fillable = [
        'uuid',
        'event_uuid',
        'email',
        'invitation_token',
        'registered_at',
    ];

    protected $casts = [
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
            
            Log::info("Created invitation for {$invitation->email} to event {$invitation->event_uuid}");
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_uuid', 'uuid');
    }
    
    public function user()
    {
        return User::where('email', $this->email)->first();
    }
    
    public function isRegistered()
    {
        return !is_null($this->registered_at);
    }
}
