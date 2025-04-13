<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserChallange extends Model
{
    protected $fillable = [
        'user_uuid',
        'name',
        'description',
        'category_uuid',
        'difficulty',
        'flag',
        'challange_file',
        'answer_file',
        'notes',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function category()
    {
        return $this->belongsTo(ChallangeCategory::class, 'category_uuid', 'uuid');
    }


}
