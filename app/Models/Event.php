<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'title',
        'description',
        'image',
        'visible_start_date',
        'start_date',
        'end_date',
    ];
    public static function boot()
    {
        parent::boot();

        static::creating(function ($data) {
            $data->uuid = (string) \Illuminate\Support\Str::uuid();
        });

    }   
    protected $casts = [
        'visible_start_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
