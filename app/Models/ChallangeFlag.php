<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallangeFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'challange_id',
        'flag',
        'bytes',
        'firstBloodBytes',
        'name',
        'ar_name',
        'description',
    ];

    protected $casts = [
        'bytes' => 'integer',
        'firstBloodBytes' => 'integer',
    ];

    public function challange()
    {
        return $this->belongsTo(Challange::class);
    }
}
