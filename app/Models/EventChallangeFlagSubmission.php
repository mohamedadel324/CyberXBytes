<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EventChallangeFlagSubmission extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $table = 'event_challange_flag_submissions';
    
    protected $fillable = [
        'event_challange_flag_id',
        'user_uuid',
        'submission',
        'solved',
        'attempts',
        'solved_at'
    ];

    protected $casts = [
        'solved' => 'boolean',
        'attempts' => 'integer',
        'solved_at' => 'datetime'
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($data) {
            $data->id = (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function eventChallangeFlag()
    {
        return $this->belongsTo(EventChallangeFlag::class, 'event_challange_flag_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
}
