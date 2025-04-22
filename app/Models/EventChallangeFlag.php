<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EventChallangeFlag extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $table = 'event_challange_flags';
    
    protected $fillable = [
        'event_challange_id',
        'flag',
        'bytes',
        'firstBloodBytes',
        'name',
        'ar_name',
        'description',
        'order'
    ];

    protected $casts = [
        'bytes' => 'integer',
        'firstBloodBytes' => 'integer',
        'order' => 'integer'
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($data) {
            $data->id = (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function eventChallange()
    {
        return $this->belongsTo(EventChallange::class, 'event_challange_id', 'id');
    }

    public function submissions()
    {
        return $this->hasMany(EventChallangeFlagSubmission::class, 'event_challange_flag_id', 'id');
    }

    public function solvedBy()
    {
        return $this->belongsToMany(User::class, 'event_challange_flag_submissions', 'event_challange_flag_id', 'user_uuid', 'id', 'uuid')
            ->wherePivot('solved', true)
            ->withPivot(['solved_at', 'attempts'])
            ->withTimestamps();
    }
}
