<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Challange extends Model
{
    protected $fillable = [
        'lab_category_uuid',
        'category',
        'key_words',
        'title',
        'description',
        'image',
        'difficulty',
        'bytes',
        'file',
        'link',
    ];
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
    protected $hidden = [
        'id',
        'updated_at',
    ];
    protected $casts = [
        'key_words' => 'array',
    ];
}
