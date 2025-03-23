<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabCategory extends Model
{
    protected $fillable = [
        'lab_uuid',
        'title',
        'image',
    ];

    public function lab()
    {
        return $this->belongsTo(Lab::class, 'lab_uuid', 'uuid');
    }

    public function challanges()
    {
        return $this->hasMany(Challange::class , 'lab_category_uuid', 'uuid');
    }
    public static function boot()
    {
        parent::boot();

        static::creating(function ($data) {
            $data->uuid = (string) \Illuminate\Support\Str::uuid();
        });

    }
    protected $hidden = [
        'id',
        'created_at',
        'updated_at',
    ];
}
