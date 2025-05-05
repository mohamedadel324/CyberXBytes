<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EventChallenge extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $table = 'event_challanges';

    protected $fillable = [
        'event_uuid',
        'category_uuid',
        'title',
        'description',
        'difficulty',
        'bytes',
        'keywords',
        'firstBloodBytes',
        'flag',
        'file',
        'link'
    ];

    protected $casts = [
        'bytes' => 'integer',
        'firstBloodBytes' => 'integer',
        'keywords' => 'array'
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_uuid', 'uuid');
    }

    public function category()
    {
        return $this->belongsTo(ChallangeCategory::class, 'category_uuid', 'uuid');
    }

    public function submissions()
    {
        return $this->hasMany(EventChallangeSubmission::class, 'event_challenge_id', 'id');
    }
}
