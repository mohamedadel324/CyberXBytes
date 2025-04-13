<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EventChallange extends Model
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
        'firstBloodBytes',
        'flag',
        'file',
        'link',
        'flag_type'
    ];

    protected $casts = [
        'bytes' => 'integer',
        'firstBloodBytes' => 'integer',
        'flag_type' => 'string'
    ];

    protected $appends = ['category_icon_url'];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($data) {
            $data->id = (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_uuid', 'uuid');
    }

    public function category()
    {
        return $this->belongsTo(ChallangeCategory::class, 'category_uuid', 'uuid');
    }

    public function getCategoryIconUrlAttribute()
    {
        return $this->category->icon ? asset('storage/' . $this->category->icon) : null;
    }

    public function submissions()
    {
        return $this->hasMany(EventChallangeSubmission::class, 'event_challange_id', 'id');
    }

    public function solvedBy()
    {
        return $this->belongsToMany(User::class, 'event_challange_submissions', 'event_challange_id', 'user_uuid', 'id', 'uuid')
            ->wherePivot('solved', true)
            ->withPivot(['solved_at', 'attempts'])
            ->withTimestamps();
    }

    public function flags()
    {
        return $this->hasMany(EventChallangeFlag::class, 'event_challange_id', 'id');
    }

    public function usesMultipleFlags()
    {
        return in_array($this->flag_type, ['multiple_all', 'multiple_individual']);
    }
    
    public function usesIndividualFlagPoints()
    {
        return $this->flag_type === 'multiple_individual';
    }

    protected $hidden = [
        'id',
        'updated_at',
    ];
}
