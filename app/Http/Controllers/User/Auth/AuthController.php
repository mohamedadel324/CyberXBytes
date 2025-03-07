<?php

namespace App\Http\Controllers\User\Auth;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Otp;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\RegistrationOtpMail;
use App\Mail\ResetPasswordOtpMail;
use Illuminate\Support\Facades\URL;

/**
 * @group Authentication
 */
class AuthController extends \Illuminate\Routing\Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register', 'sendResetLinkEmail', 'resetPassword', 'verifyEmail', 'verifyRegistrationOtp']]);
    }

    /**
     * Register a new user
     * 
     * @bodyParam name string required The name of the user. Example: John Doe
     * @bodyParam email string required The email of the user. Example: john@example.com
     * @bodyParam password string required The password for the account. Example: password123
     * 
     * @return \Illuminate\Http\JsonResponse
     * @unauthenticated
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'between:2,100'],
            'email' => ['required', 'string', 'email', 'max:100', 'unique:users'],
            'user_name' => ['required', 'string', 'between:2,100', 'unique:users'],
            'city' => ['required', 'string', 'between:2,100'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::create(array_merge(
            $request->all(),
            [
                'email_verified_at' => null,
                'status' => 'pending'
            ]
        ));

        // Generate and send OTP
        $this->generateAndSendRegistrationOtp($user);

        // Generate temporary token
        $token = auth()->login($user);

        return response()->json([
            'message' => 'Registration initiated. Please verify your account with the OTP sent to your email.',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => $user
        ]);
    }

    /**
     * Login user and create token
     * 
     * @bodyParam email string required The email of the user. Example: john@example.com
     * @bodyParam password string required The password for the account. Example: password123
     * 
     * @response {
     *   "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
     *   "token_type": "bearer",
     *   "expires_in": 3600
     * }
     * 
     * @return \Illuminate\Http\JsonResponse
     * @unauthenticated
     */
    public function login(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $credentials = $request->only('email', 'password');

        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Generate and send OTP
        $user = auth()->user();
        if ($user->email_verified_at === null) {
            $this->generateAndSendRegistrationOtp($user);
            return response()->json([
                'message' => 'Registration initiated. Please verify your account with the OTP sent to your email.',
                'temp_token' => $token
            ]);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated User
     *
     * @authenticated
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    /**
     * Log the user out (Invalidate the token)
     *
     * @authenticated
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }

    /**
     * Send a password reset link to the user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @unauthenticated
     */
    public function sendResetLinkEmail(Request $request)
    {
        try {
            $request->validate(['email' => 'required|email|exists:users,email']);

            $user = User::where('email', $request->email)->first();
            
            // Generate and send OTP
            $this->generateAndSendPasswordResetOtp($user);

            return response()->json([
                'status' => 'success',
                'message' => 'Password reset OTP has been sent to your email.',
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send reset OTP. Please try again.'
            ], 500);
        }
    }

    private function generateAndSendPasswordResetOtp($user)
    {
        // Delete any existing OTP for this user
        Otp::where('user_id', $user->id)->delete();

        // Generate new 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in database
        Otp::create([
            'user_id' => $user->id,
            'otp' => bcrypt($otp),
            'expires_at' => now()->addMinutes(2),
            'attempts' => 0
        ]);

        // Send OTP via email
        Mail::to($user->email)->send(new ResetPasswordOtpMail($user, $otp));
    }

    /**
     * Reset the user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @unauthenticated
     */
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|string|size:6',
                'password' => 'required|string|min:6'
            ]);

            $user = User::where('email', $request->email)->first();
            $otpRecord = Otp::where('user_id', $user->id)->first();

            if (!$otpRecord) {
                return response()->json(['error' => 'No OTP request found'], 404);
            }

            if ($otpRecord->expires_at < now()) {
                $otpRecord->delete();
                return response()->json(['error' => 'OTP has expired'], 400);
            }

            if ($otpRecord->attempts >= 3) {
                $otpRecord->delete();
                return response()->json(['error' => 'Maximum attempts exceeded. Please request a new OTP.'], 400);
            }

            $otpRecord->attempts += 1;
            $otpRecord->save();

            if (!password_verify($request->otp, $otpRecord->otp)) {
                $remainingAttempts = 3 - $otpRecord->attempts;
                return response()->json([
                    'error' => 'Invalid OTP',
                    'remaining_attempts' => $remainingAttempts
                ], 400);
            }

            // OTP is valid - update password
            $user->password = bcrypt($request->password);
            $user->save();
            $otpRecord->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Password has been successfully reset.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reset password. Please try again.'
            ], 500);
        }
    }

    /**
     * Verify the user's email.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @param  string  $hash
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyEmail(Request $request, $id, $hash)
    {
        try {
            $user = User::findOrFail($id);

            if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid verification link!'
                ], 400);
            }

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Email already verified!'
                ]);
            }

            $user->markEmailAsVerified();

            return response()->json([
                'status' => 'success',
                'message' => 'Email successfully verified!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to verify email. Please try again.'
            ], 500);
        }
    }

    private function generateAndSendOtp($user)
    {
        // Delete any existing OTP for this user
        Otp::where('user_id', $user->id)->delete();

        // Generate new 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in database
        Otp::create([
            'user_id' => $user->id,
            'otp' => bcrypt($otp),
            'expires_at' => now()->addMinutes(2),
            'attempts' => 0
        ]);

        // Send OTP via email
        Mail::to($user->email)->send(new \App\Mail\OtpMail($otp));
    }

    private function generateAndSendRegistrationOtp($user)
    {
        // Delete any existing OTP for this user
        Otp::where('user_id', $user->id)->delete();

        // Generate new 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in database
        Otp::create([
            'user_id' => $user->id,
            'otp' => bcrypt($otp),
            'expires_at' => now()->addMinutes(2),
            'attempts' => 0
        ]);

        // Send OTP via email using the registration-specific template
        Mail::to($user->email)->send(new RegistrationOtpMail($user, $otp));
    }

    public function verifyRegistrationOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        // Get user from token
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($user->email_verified_at !== null) {
            return response()->json(['error' => 'User is already verified'], 400);
        }

        $otpRecord = Otp::where('user_id', $user->id)->first();

        if (!$otpRecord) {
            return response()->json(['error' => 'No OTP request found'], 404);
        }

        if ($otpRecord->expires_at < now()) {
            $otpRecord->delete();
            return response()->json(['error' => 'OTP has expired'], 400);
        }

        if ($otpRecord->attempts >= 3) {
            $otpRecord->delete();
            // Delete unverified user after max attempts
            if ($user->email_verified_at === null) {
                $user->delete();
                auth()->logout();
            }
            return response()->json(['error' => 'Maximum attempts exceeded. Please register again.'], 400);
        }

        $otpRecord->attempts += 1;
        $otpRecord->save();

        if (!password_verify($request->otp, $otpRecord->otp)) {
            $remainingAttempts = 3 - $otpRecord->attempts;
            return response()->json([
                'error' => 'Invalid OTP',
                'remaining_attempts' => $remainingAttempts
            ], 400);
        }

        // OTP is valid - verify user
        $user->email_verified_at = now();
        $user->status = 'active';
        $user->save();
        $otpRecord->delete();

        // Generate fresh token
        $token = auth()->refresh();

        return response()->json([
            'message' => 'Registration completed successfully',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => $user
        ]);
    }
}
