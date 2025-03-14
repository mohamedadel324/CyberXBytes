<?php

namespace App\Http\Controllers\User\Auth;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
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
        $this->middleware('auth:api', ['except' => ['login', 'register', 'sendResetLinkEmail', 'resetPassword', 'verifyEmail', 'verifyRegistrationOtp', 'sendVerificationOtp']]);
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


        return response()->json([
            'message' => 'Registration Successful.',
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
        $user = auth()->user()->makeHidden(['otp', 'otp_expires_at', 'otp_attempts', 'created_at', 'updated_at']);
            return response()->json([
                'message' => 'Login Successful.',
                'user' => $user,
                'token' => $token
            ]);
        

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
     * Send a password OTP  to the user.
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

    private function generateAndSendRegistrationOtp($user)
    {
        // Generate new 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store hashed OTP in users table
        $user->update([
            'otp' => hash('sha256', $otp),
            'otp_expires_at' => now()->addMinutes(5),
            'otp_attempts' => 0
        ]);

        // Send OTP via email
        Mail::to($user->email)->send(new RegistrationOtpMail($user, $otp));
    }

    private function generateAndSendPasswordResetOtp($user)
    {
        // Generate new 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store hashed OTP in users table
        $user->update([
            'otp' => hash('sha256', $otp),
            'otp_expires_at' => now()->addMinutes(5),
            'otp_attempts' => 0
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

            if (!$user->otp || !$user->otp_expires_at) {
                return response()->json(['error' => 'No OTP request found'], 404);
            }

            if ($user->otp_expires_at < now()) {
                $user->update(['otp' => null, 'otp_expires_at' => null, 'otp_attempts' => 0]);
                return response()->json(['error' => 'OTP has expired'], 400);
            }

            if ($user->otp_attempts >= 3) {
                $user->update(['otp' => null, 'otp_expires_at' => null, 'otp_attempts' => 0]);
                return response()->json(['error' => 'Maximum attempts exceeded. Please request a new OTP.'], 400);
            }

            $user->increment('otp_attempts');

            if (hash('sha256', $request->otp) !== $user->otp) {
                $remainingAttempts = 3 - $user->otp_attempts;
                return response()->json([
                    'error' => 'Invalid OTP',
                    'remaining_attempts' => $remainingAttempts
                ], 400);
            }

            // OTP is valid - update password
            $user->update([
                'password' => bcrypt($request->password),
                'otp' => null,
                'otp_expires_at' => null,
                'otp_attempts' => 0
            ]);

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

    /**
     * Verify OTP and activate user account
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyRegistrationOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' => 'Email already verified',
                'user' => $user->makeHidden(['otp', 'otp_expires_at', 'otp_attempts', 'created_at', 'updated_at'])
            ]);
        }

        if (!$user->otp || !$user->otp_expires_at) {
            return response()->json([
                'error' => 'Please request an OTP first',
                'code' => 'NO_OTP_FOUND'
            ], 404);
        }

        if ($user->otp_expires_at < now()) {
            $user->update(['otp' => null, 'otp_expires_at' => null, 'otp_attempts' => 0]);
            return response()->json([
                'error' => 'OTP has expired. Please request a new one',
                'code' => 'OTP_EXPIRED'
            ], 400);
        }

        if ($user->otp_attempts >= 3) {
            $user->update(['otp' => null, 'otp_expires_at' => null, 'otp_attempts' => 0]);
            return response()->json([
                'error' => 'Maximum attempts exceeded. Please request a new OTP',
                'code' => 'MAX_ATTEMPTS_EXCEEDED'
            ], 400);
        }

        $user->increment('otp_attempts');

        if (hash('sha256', $request->otp) !== $user->otp) {
            $remainingAttempts = 3 - $user->otp_attempts;
            return response()->json([
                'error' => 'Invalid OTP',
                'remaining_attempts' => $remainingAttempts,
                'code' => 'INVALID_OTP'
            ], 400);
        }

        // OTP is valid - verify user
        $user->update([
            'email_verified_at' => now(),
            'status' => 'active',
            'otp' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0
        ]);
        return response()->json([
            'message' => 'Email verified successfully',
            'user' => $user->makeHidden(['otp', 'otp_expires_at', 'otp_attempts', 'created_at', 'updated_at']),
        ]);
    }

    /**
     * Send verification OTP to user's email
     * 
     * @authenticated
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendVerificationOtp()
    {
        $user = auth()->user();

        if ($user->email_verified_at !== null) {
            return response()->json(['error' => 'Email is already verified'], 400);
        }

        // Check if user has exceeded maximum attempts (3)
        if ($user->otp_attempts >= 3) {
            return response()->json([
                'error' => 'Maximum verification attempts exceeded. Please contact support.',
                'remaining_attempts' => 0
            ], 400);
        }

        // Check if previous OTP was sent within last 60 seconds
        if ($user->otp_expires_at && now()->subSeconds(60)->lt($user->otp_expires_at)) {
            $waitTime = now()->diffInSeconds($user->otp_expires_at->subSeconds(300));
            return response()->json([
                'error' => 'Please wait before requesting another OTP',
                'wait_seconds' => $waitTime
            ], 429);
        }

        // Generate new 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store hashed OTP in users table
        $user->update([
            'otp' => hash('sha256', $otp),
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        // Send OTP via email
        Mail::to($user->email)->send(new RegistrationOtpMail($user, $otp));

        return response()->json([
            'message' => 'Verification OTP has been sent to your email',
            'expires_in' => 300, // 5 minutes
            'remaining_attempts' => 3 - $user->otp_attempts,
            'next_request_allowed_in' => 60 // seconds
        ]);
    }
}
