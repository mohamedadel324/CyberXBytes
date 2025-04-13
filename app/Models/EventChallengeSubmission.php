<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EventChallengeSubmission extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $table = 'event_challange_submissions';

    protected $fillable = [
        'event_challenge_id',
        'team_uuid',
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

    public function eventChallenge()
    {
        return $this->belongsTo(EventChallange::class, 'event_challenge_id', 'id');
    }

    public function team()
    {
        return $this->belongsTo(EventTeam::class, 'team_uuid', 'id');
    }
}
