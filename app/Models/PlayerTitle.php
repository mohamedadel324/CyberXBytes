<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PlayerTitle extends Model
{
    use HasFactory;

    protected $fillable = [
        'title_ranges'
    ];

    protected $casts = [
        'title_ranges' => 'array'
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'current_title_id');
    }

    public static function getTitleForPercentage($percentage)
    {
        $titleConfig = static::first();
        if (!$titleConfig) {
            return [
                'title' => null,
                'arabic_title' => null
            ];
        }

        foreach ($titleConfig->title_ranges as $range) {
            if ($percentage >= $range['from'] && $percentage <= $range['to']) {
                return [
                    'title' => $range['title'],
                    'arabic_title' => $range['arabic_title']
                ];
            }
        }

        return [
            'title' => null,
            'arabic_title' => null
        ];
    }
}
