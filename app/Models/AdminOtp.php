<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class AdminOtp extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'otp',
        'expires_at',
        'attempts'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Verify if the given OTP matches the stored OTP.
     *
     * @param string $otp
     * @return bool
     */
    public function verifyOtp($otp)
    {
        // For simplicity, we're doing a direct comparison
        return $this->otp === $otp;
    }

    /**
     * Check if the OTP has expired.
     *
     * @return bool
     */
    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    /**
     * Get the admin that owns the OTP.
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
