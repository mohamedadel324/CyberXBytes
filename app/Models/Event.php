<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\EventRegistration;
use App\Mail\EventRegistrationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

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
        'invited_emails',
        'freeze'
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
        'freeze' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            $event->uuid = (string) Str::uuid();
        });

        // Handle invitations after event creation
        static::created(function ($event) {
            if ($event->is_private && !empty($event->invited_emails)) {
                Log::info('Processing invitations for private event: ' . $event->title);
                
                $emails = $event->invited_emails;
                if (is_string($emails)) {
                    $emails = json_decode($emails, true) ?? [];
                }
                
                Log::info('Processing ' . count($emails) . ' invitations for event: ' . $event->uuid);
                
                foreach ($emails as $email) {
                    $event->addUserByEmail($email);
                }
            }
        });

        // Ensure only one event can be marked as main
        static::saved(function ($event) {
            if ($event->is_main) {
                static::where('id', '!=', $event->id)->update(['is_main' => false]);
            }

            // Process any additional invitations when event is updated
            if ($event->is_private && !empty($event->invited_emails) && $event->wasChanged('invited_emails')) {
                $emails = $event->invited_emails;
                if (is_string($emails)) {
                    $emails = json_decode($emails, true) ?? [];
                }
                
                Log::info('Processing updated invitations for event: ' . $event->uuid);
                
                foreach ($emails as $email) {
                    $event->addUserByEmail($email);
                }
            }
        });
    }

    /**
     * Add a user to this event by email address
     * 
     * @param string $email
     * @return void
     */
    public function addUserByEmail($email)
    {
        Log::info("Adding user by email to event {$this->uuid}: {$email}");
        
        // Create invitation record
        $invitation = EventInvitation::firstOrCreate([
            'event_uuid' => $this->uuid,
            'email' => $email,
        ]);

        // Find the user
        $user = User::where('email', $email)->first();
        if ($user) {
            Log::info("User found for email {$email}, registering them");
            
            // Register the user
            $registration = EventRegistration::firstOrCreate([
                'event_uuid' => $this->uuid,
                'user_uuid' => $user->uuid,
            ], [
                'status' => 'registered'
            ]);
            
            // Mark invitation as registered
            if (!$invitation->registered_at) {
                $invitation->registered_at = now();
                $invitation->save();
                
                // Send email
                try {
                    Mail::to($user->email)->send(new EventRegistrationMail($this, $user));
                    Log::info("Email sent to {$email} for event {$this->title}");
                } catch (\Exception $e) {
                    Log::error("Failed to send email to {$email}: " . $e->getMessage());
                }
            }
        } else {
            Log::info("No user found for email {$email}");
        }
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
        return $this->hasMany(EventRegistration::class , 'event_uuid', 'uuid');
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
