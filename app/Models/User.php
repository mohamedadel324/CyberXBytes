<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\Event;
use App\Models\EventChallange;
use App\Models\EventChallangeSubmission;
use App\Models\EventChallangeFlag;
use App\Models\EventChallangeFlagSubmission;
use App\Models\UserSocialMedia;
use App\Models\UserOtp;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
  // Rest omitted for brevity

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
        'user_name',
        'profile_image',
        'country',
        'status',
        'email_verified_at',
        'time_zone',
        'last_seen'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            $user->uuid = (string) \Illuminate\Support\Str::uuid();
            
            // Set default timezone if not provided
            if (!isset($user->time_zone)) {
                $user->time_zone = 'UTC';
            }
        });
        // static::retrieved(function ($user) {
        //     if ($user->profile_image) {
        //         $user->profile_image = asset('storage/' . $user->profile_image);
        //     }
        // });

    }


    public function socialMedia()
    {
        return $this->hasMany(UserSocialMedia::class, 'user_uuid', 'uuid');
    }

    public function otp()
    {
        return $this->hasOne(UserOtp::class, 'user_uuid', 'uuid');
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class, 'user_uuid', 'uuid');
    }

    public function eventSubmissions()
    {
        return $this->hasMany(EventChallangeSubmission::class, 'user_uuid', 'uuid');
    }

    public function solvedChallenges()
    {
        return $this->belongsToMany(EventChallange::class, 'event_challange_submissions', 'user_uuid', 'event_challange_id', 'uuid', 'id')
            ->wherePivot('solved', true)
            ->withPivot(['solved_at', 'attempts'])
            ->withTimestamps();
    }
    
    public function flagSubmissions()
    {
        return $this->hasMany(EventChallangeFlagSubmission::class, 'user_uuid', 'uuid');
    }
    
    public function solvedFlags()
    {
        return $this->belongsToMany(EventChallangeFlag::class, 'event_challange_flag_submissions', 'user_uuid', 'event_challange_flag_id', 'uuid', 'id')
            ->wherePivot('solved', true)
            ->withPivot(['solved_at', 'attempts'])
            ->withTimestamps();
    }

    public function registeredEvents()
    {
        return $this->belongsToMany(Event::class, 'event_registrations', 'user_uuid', 'event_uuid', 'uuid', 'uuid');
    }

    /**
     * Get category completion data excluding event challenges
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getCategoryCompletionAttribute()
    {
        // Get all non-event categories
        $categories = ChallangeCategory::all();
        
        // Get user's solved challenges (regular challenges only, not event challenges)
        $userSolvedChallenges = Challange::whereHas('submissions', function($query) {
            $query->where('user_uuid', $this->uuid)
                ->where('solved', true);
        })->get();
        
        $result = collect();
        
        foreach ($categories as $category) {
            // Get total challenges for this category (excluding event challenges)
            $totalChallenges = Challange::where('category_uuid', $category->uuid)->count();
            
            // Skip categories with no challenges
            if ($totalChallenges === 0) {
                continue;
            }
            
            // Count how many challenges in this category the user has solved
            $solvedChallenges = $userSolvedChallenges->where('category_uuid', $category->uuid)->count();
            
            // Calculate completion percentage
            $percentage = ($solvedChallenges > 0 && $totalChallenges > 0) 
                ? round(($solvedChallenges / $totalChallenges) * 100) 
                : 0;
            
            // Add to result collection
            $result->push([
                'name' => $category->name,
                'solved_count' => $solvedChallenges,
                'total_count' => $totalChallenges,
                'percentage' => $percentage,
            ]);
        }
        
        // Sort by percentage completion (highest first)
        return $result->sortByDesc('percentage')->values();
    }
    
    /**
     * Get the unsolved challenges for this user (regular challenges only, not event challenges)
     */
    public function getUnsolvedChallengesAttribute()
    {
        // Get IDs of challenges this user has solved
        $solvedIds = Challange::whereHas('submissions', function($query) {
            $query->where('user_uuid', $this->uuid)
                ->where('solved', true);
        })->pluck('id');
        
        // Return challenges not in the solved IDs list
        return Challange::whereNotIn('id', $solvedIds)
            ->with('category')
            ->get();
    }
    
    public function getCurrentTitleAttribute()
    {
        $totalChallenges = EventChallange::count();
        if ($totalChallenges === 0) {
            return null;
        }

        $solvedChallenges = $this->solvedChallenges()->count();
        $percentage = ($solvedChallenges / $totalChallenges) * 100;
        
        return PlayerTitle::getTitleForPercentage($percentage);
    }
}
