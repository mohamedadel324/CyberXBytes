<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallangeCategory extends Model
{
    protected $fillable = [
        'name',
        'icon',
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function challanges()
    {
        return $this->hasMany(Challange::class, 'category_uuid', 'uuid');
    }

    protected $hidden = [
        'id',
        'created_at',
        'updated_at',
    ];
}
