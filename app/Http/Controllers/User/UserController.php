<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserSocialMedia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function profile(Request $request)
    {
        return response()->json(['user' => $request->user()->makeHidden(['otp', 'otp_expires_at', 'otp_attempts', 'id'])]);
    }

    public function changeProfileData(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . auth('api')->user()->id,
            'user_name' => 'sometimes|required|string|max:255|unique:users,user_name,' . auth('api')->user()->id,
            'country' => 'sometimes|required|string|max:255',
        ]);
        if (isset($validatedData['email']) && $validatedData['email'] !== $request->user()->email) {
            $validatedData['email_verified_at'] = null;
        }

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
            'new_password' => 'required|string|min:8',
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
}
