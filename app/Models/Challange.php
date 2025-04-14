<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Challange extends Model
{
    protected $fillable = [
        'lab_category_uuid',
        'category_uuid',
        'title',
        'description',
        'difficulty',
        'bytes',
        'file',
        'link',
        'firstBloodBytes',
        'flag',
        'flag_type',
        'made_by'
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
            $data->uuid = (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function labCategory()
    {   
        return $this->belongsTo(LabCategory::class, 'lab_category_uuid', 'uuid');
    }

    public function category()
    {
        return $this->belongsTo(ChallangeCategory::class, 'category_uuid', 'uuid');
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class, 'challange_uuid', 'uuid');
    }

    public function flags()
    {
        return $this->hasMany(ChallangeFlag::class, 'challange_id', 'id');
    }

    public function usesMultipleFlags()
    {
        return in_array($this->flag_type, ['multiple_all', 'multiple_individual']);
    }
    
    public function usesIndividualFlagPoints()
    {
        return $this->flag_type === 'multiple_individual';
    }

    public function getCategoryIconUrlAttribute()
    {
        return $this->category->icon ? asset('storage/' . $this->category->icon) : null;
    }

    protected $hidden = [
        'id',
        'updated_at',
    ];
}
