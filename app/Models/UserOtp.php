<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UserOtp extends Model
{
    protected $fillable = [
        'user_id',
        'otp',
        'expires_at',
        'attempts'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'otp',
        'user_id',
        'expires_at',
        'attempts',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Set the OTP value, automatically hashing it.
     *
     * @param string $value
     * @return void
     */
    public function setOtpAttribute($value)
    {
        $this->attributes['otp'] = Hash::make($value);
    }

    /**
     * Verify if the given OTP matches the stored hash.
     *
     * @param string $otp
     * @return bool
     */
    public function verifyOtp($otp)
    {
        return Hash::check($otp, $this->otp);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
