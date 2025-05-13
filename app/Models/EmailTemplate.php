<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'subject',
        'header_text',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
} 