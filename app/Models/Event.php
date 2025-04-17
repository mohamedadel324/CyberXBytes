<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

class Event extends Model
{
    protected $fillable = [
        'title',
        'description',
        'background_image',
        'image',
        'is_private',
        'is_main',
        'registration_start_date',
        'registration_end_date',
        'team_formation_start_date',
        'team_formation_end_date',
        'visible_start_date',
        'start_date',
        'end_date',
        'requires_team',
        'team_minimum_members',
        'team_maximum_members',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'is_main' => 'boolean',
        'requires_team' => 'boolean',
        'registration_start_date' => 'datetime',
        'registration_end_date' => 'datetime',
        'team_formation_start_date' => 'datetime',
        'team_formation_end_date' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'invited_emails' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            $event->uuid = (string) Str::uuid();
        });

        // Handle invitations after event creation
        static::created(function ($event) {
            if ($event->is_private) {
                $pendingInvitations = session('pending_invitations', []);
                foreach ($pendingInvitations as $email) {
                    $invitation = EventInvitation::create([
                        'event_uuid' => $event->uuid,
                        'email' => $email,
                    ]);

                    // Send invitation email
                    Mail::to($email)->queue(new EventInvitationMail($invitation));
                    
                    $invitation->update(['email_sent_at' => now()]);
                }
                // Clear the pending invitations
                session()->forget('pending_invitations');
            }
        });

        // Ensure only one event can be marked as main
        static::saved(function ($event) {
            if ($event->is_main) {
                static::where('id', '!=', $event->id)->update(['is_main' => false]);
            }
        });
    }

    public function invitations()
    {
        return $this->hasMany(EventInvitation::class, 'event_uuid', 'uuid');
    }

    public function isUserInvited($email)
    {
        return $this->is_private ? $this->invitations()->where('email', $email)->exists() : true;
    }

    public function scopeVisibleToUser($query, $email = null)
    {
        return $query->where(function ($q) use ($email) {
            $q->where('is_private', false)
              ->orWhereHas('invitations', function ($q) use ($email) {
                  $q->where('email', $email);
              });
        });
    }

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function teams()
    {
        return $this->hasMany(EventTeam::class);
    }

    public function challenges()
    {
        return $this->hasMany(EventChallange::class, 'event_uuid', 'uuid');
    }

    public function registeredUsers()
    {
        return $this->belongsToMany(User::class, 'event_registrations');
    }

    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    /**
     * Get the main event
     * 
     * @return Event|null
     */
    public static function getMainEvent()
    {
        return static::main()->first();
    }
}
