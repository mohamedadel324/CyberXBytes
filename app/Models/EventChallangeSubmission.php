<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EventChallangeSubmission extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'event_challange_id',
        'user_uuid',
        'submission',
        'solved',
        'attempts',
        'solved_at',
        'ip',
    ];

    protected $casts = [
        'solved' => 'boolean',
        'attempts' => 'integer',
        'solved_at' => 'datetime'
    ];

    public function eventChallange()
    {
        return $this->belongsTo(EventChallange::class, 'event_challange_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
}
