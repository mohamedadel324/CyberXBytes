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

    public function getCategoryIconUrlAttribute()
    {
        return $this->category->icon ? asset('storage/' . $this->category->icon) : null;
    }

    protected $hidden = [
        'id',
        'updated_at',
    ];
}
