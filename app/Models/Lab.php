<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lab extends Model
{
    protected $fillable = [
        'name',
    ];
    
    protected $hidden = [
        'id',
        'created_at',
        'updated_at',
    ];
    public static function boot()
    {
        parent::boot();

        static::creating(function ($data) {
            $data->uuid = (string) \Illuminate\Support\Str::uuid();
        });
    }
    public function labCategories()
    {
        return $this->hasMany(LabCategory::class, 'lab_uuid', 'uuid');
    }
}
    
