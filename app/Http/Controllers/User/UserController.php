<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserSocialMedia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Mail\RegistrationOtpMail;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();
        $user->profile_image = url('storage/' . $user->profile_image);
        $user->makeHidden(['otp', 'otp_expires_at', 'otp_attempts', 'id']);
        
        return response()->json(['user' => $user]);
    }

    public function changeProfileData(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'user_name' => 'sometimes|required|string|max:255|unique:users,user_name,' . auth('api')->user()->id,
            'country' => 'sometimes|required|string|max:255',
        ]);

        $request->user()->update($validatedData);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $request->user()->makeHidden(['otp', 'otp_expires_at', 'otp_attempts', 'id'])
        ]);
    }

    public function changePassword(Request $request)
    {
        $validatedData = $request->validate([
            'current_password' => 'required',
            'new_password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/'],
        ]);

        $user = $request->user();

        if (!Hash::check($validatedData['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect'],
            ]);
        }

        $user->update(['password' => Hash::make($validatedData['new_password'])]);

        return response()->json(['message' => 'Password changed successfully']);
    }

    public function changeSocialMediaLinks(Request $request)
    {
        $validatedData = $request->validate([
            'discord' => 'nullable|string',
            'instagram' => 'nullable|string',
            'twitter' => 'nullable|string',
            'tiktok' => 'nullable|string',
            'youtube' => 'nullable|string',
        ]);

        $socialMedia = $request->user()->socialMedia()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $validatedData
        );

        return response()->json([
            'message' => 'Social media links updated successfully',
            'social_media' => $socialMedia
        ]);
    }

    /**
     * @requestMediaType multipart/form-data
     */
    public function changeProfileImage(Request $request){
        $request->validate([
            'profile_image' => 'required|image'
        ]);
        $file = $request->file('profile_image');
        $path = $file->store('profile_images', 'public');
        $validatedData['profile_image'] = $path;
        $request->user()->update($validatedData);

        return response()->json([
            'message' => 'Profile image updated successfully',
            'profile_image' => asset('storage/' . $path)
        ]);
    }

    /**
     * Request email change by sending OTP to new email
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestEmailChange(Request $request)
    {
        try {
            $request->validate([
                'new_email' => 'required|email|unique:users,email'
            ]);

            $user = auth()->user();
            
            // Generate OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Store OTP and new email in cache
            $cacheKey = "email_change:{$user->id}";
            Cache::put($cacheKey, [
                'new_email' => $request->new_email,
                'otp' => $otp,
                'expires_at' => now()->addMinutes(5),
                'attempts' => 0
            ], now()->addMinutes(30));

            // Send OTP to new email
            Mail::to($request->new_email)->send(new RegistrationOtpMail(['email' => $request->new_email], $otp));

            return response()->json([
                'message' => 'OTP has been sent to your new email address.',
                'expires_in' => 300 // 5 minutes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send OTP. Please try again.'
            ], 500);
        }
    }

    /**
     * Verify OTP and change email
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyEmailChange(Request $request)
    {
        try {
            $request->validate([
                'otp' => 'required|string|size:6'
            ]);

            $user = auth()->user();
            $cacheKey = "email_change:{$user->id}";
            $otpData = Cache::get($cacheKey);

            if (!$otpData) {
                return response()->json([
                    'error' => 'OTP session expired. Please request a new OTP.',
                    'code' => 'SESSION_EXPIRED'
                ], 404);
            }

            if ($otpData['expires_at'] < now()) {
                Cache::forget($cacheKey);
                return response()->json([
                    'error' => 'OTP has expired. Please request a new OTP.',
                    'code' => 'OTP_EXPIRED'
                ], 400);
            }

            if ($otpData['attempts'] >= 3) {
                Cache::forget($cacheKey);
                return response()->json([
                    'error' => 'Maximum attempts exceeded. Please request a new OTP.',
                    'code' => 'MAX_ATTEMPTS_EXCEEDED'
                ], 400);
            }

            // Increment attempts
            $otpData['attempts']++;
            Cache::put($cacheKey, $otpData, now()->addMinutes(30));

            if ($request->otp !== $otpData['otp']) {
                $remainingAttempts = 3 - $otpData['attempts'];
                return response()->json([
                    'error' => 'Invalid OTP',
                    'remaining_attempts' => $remainingAttempts,
                    'code' => 'INVALID_OTP'
                ], 400);
            }

            // Update user's email and mark as verified
            $user->update([
                'email' => $otpData['new_email'],
                'email_verified_at' => now()
            ]);

            // Clean up cache
            Cache::forget($cacheKey);

            return response()->json([
                'message' => 'Email changed successfully.',
                'user' => $user->makeHidden(['otp', 'otp_expires_at', 'otp_attempts', 'id'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to verify OTP. Please try again.'
            ], 500);
        }
    }
}
