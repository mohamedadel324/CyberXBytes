<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermsPrivacy extends Model
{
    use HasFactory;

    protected $fillable = [
        'terms_content',
        'privacy_content',
    ];

    protected $casts = [
        'terms_content' => 'string',
        'privacy_content' => 'string',
    ];
} 