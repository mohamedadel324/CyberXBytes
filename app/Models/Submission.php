<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $fillable = [
        'challange_uuid',
        'user_uuid',
        'flag',
        'solved',
        'ip',
    ];
    
    public function challange()
    {
        return $this->belongsTo(Challange::class, 'challange_uuid', 'uuid');
    }


    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
    public static function boot()
    {
        parent::boot();

        static::creating(function ($data) {
            $data->uuid = (string) \Illuminate\Support\Str::uuid();
        });

    }   
    protected $casts = [
        'solved' => 'boolean',
    ];
    protected $hidden = [
        'id',
        'created_at',
        'updated_at',
    ];
}
