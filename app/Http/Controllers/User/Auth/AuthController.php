<?php

namespace App\Http\Controllers\User\Auth;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\UserOtp;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\RegistrationOtpMail;
use App\Mail\ResetPasswordOtpMail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Cache;

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
        $this->middleware('auth:api', ['except' => ['login', 'register', 'sendResetLinkEmail', 'resetPassword', 'verifyEmail', 'verifyRegistrationOtp', 'sendVerificationOtp', 'verifyPasswordResetOtp']]);
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
            'email' => ['required', 'string', 'email', 'max:50', 'unique:users', 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'],
            'user_name' => ['required', 'string', 'between:2,15', 'unique:users', 'regex:/^[a-zA-Z0-9]+$/'],
            'country' => ['required', 'string', 'between:2,10', 'regex:/^[a-zA-Z0-9\s\-\,\.\']+$/'],
            'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/'],
        ]);

        // Generate a unique registration ID
        $registrationId = Str::uuid()->toString();
        
        // Store registration data in cache for 30 minutes
        $registrationData = array_merge(
            $request->all(),
            [
                'password' => bcrypt($request->password),
                'email_verified_at' => null
            ]
        );
        
        Cache::put("registration:{$registrationId}", $registrationData, now()->addMinutes(30));

        // Generate OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP data in cache (store plain OTP for verification)
        Cache::put("registration_otp:{$registrationId}", [
            'otp' => $otp, // Store plain OTP
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0
        ], now()->addMinutes(30));

        // Send OTP via email synchronously
        Mail::to($request->email)->send(new RegistrationOtpMail(['email' => $request->email], $otp));

        return response()->json([
            'message' => 'Registration initiated. Please verify your email with the OTP sent.',
            'registration_id' => $registrationId,
            'expires_in' => 300 // 5 minutes
        ]);
    }

    /**
     * Verify OTP and activate user account
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @unauthenticated
     */
    public function verifyRegistrationOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6',
            'registration_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $registrationId = $request->registration_id;
        
        $registrationData = Cache::get("registration:{$registrationId}");
        $otpData = Cache::get("registration_otp:{$registrationId}");

        if (!$registrationData || !$otpData) {
            return response()->json([
                'error' => 'Registration session expired. Please register again.',
                'code' => 'SESSION_EXPIRED'
            ], 404);
        }

        if ($otpData['expires_at'] < now()) {
            Cache::forget("registration:{$registrationId}");
            Cache::forget("registration_otp:{$registrationId}");
            
            return response()->json([
                'error' => 'OTP has expired. Please register again.',
                'code' => 'OTP_EXPIRED'
            ], 400);
        }

        if ($otpData['attempts'] >= 3) {
            Cache::forget("registration:{$registrationId}");
            Cache::forget("registration_otp:{$registrationId}");
            
            return response()->json([
                'error' => 'Maximum attempts exceeded. Please register again.',
                'code' => 'MAX_ATTEMPTS_EXCEEDED'
            ], 400);
        }

        // Increment attempts in cache
        $otpData['attempts']++;
        Cache::put("registration_otp:{$registrationId}", $otpData, now()->addMinutes(30));

        // Simple string comparison since we store the plain OTP
        if ($request->otp !== $otpData['otp']) {
            $remainingAttempts = 3 - $otpData['attempts'];
            return response()->json([
                'error' => 'Invalid OTP',
                'remaining_attempts' => $remainingAttempts,
                'code' => 'INVALID_OTP'
            ], 400);
        }

        // Create the verified user
        $user = User::create(array_merge(
            $registrationData,
            ['email_verified_at' => now()]
        ));

        // Clean up cache
        Cache::forget("registration:{$registrationId}");
        Cache::forget("registration_otp:{$registrationId}");

        $token = auth()->login($user);

        return response()->json([
            'message' => 'Registration completed successfully.',
            'user' => $user->makeHidden(['created_at', 'updated_at']),
            'token' => $token
        ]);
    }

    /**
     * Login
     * 
     * @bodyParam email string required The email of the user. Example: john@example.com
     * @bodyParam password string required The password for the account. Example: password123
     * 
     * @response {
     *   "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
     *   "token_type": "bearer",
     *   "expires_in": 3600,
     *   "bound_ip": "127.0.0.1"
     * }
     * 
     * @return \Illuminate\Http\JsonResponse
     * @unauthenticated
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $loginField = filter_var($request->input('login'), FILTER_VALIDATE_EMAIL) ? 'email' : 'user_name';
        $credentials = [
            $loginField => $request->input('login'),
            'password' => $request->input('password'),
        ];

        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Email or password is incorrect'], 401);
        }

        $user = auth()->user()->makeHidden(['created_at', 'updated_at']);
        $user->last_seen = now();
        $user->save();
        return response()->json([
            'message' => 'Login Successful.',
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * User Data
     *
     * @authenticated
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    /**
     * Invalidate the token
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
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     * @authenticated
     */
    public function refresh()
    {
        // Get a new token with the current IP address
        return $this->respondWithToken(auth()->refresh());
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
        // Store the IP address associated with this token
        $ip = request()->ip();
        Cache::put('token_ip:' . $token, $ip, auth()->factory()->getTTL() * 60);
        
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'bound_ip' => $ip
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
        $request->validate(['email' => 'required|email|exists:users,email']);

        $user = User::where('email', $request->email)->first();
        
        $cacheKey = 'password_reset_' . $user->id;
        if (Cache::has($cacheKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please wait 60 seconds before requesting another OTP.',
            ], 429);
        }

        $this->generateAndSendPasswordResetOtp($user);

        Cache::put($cacheKey, true, 60);

        return response()->json([
            'status' => 'success',
            'message' => 'Password reset OTP has been sent to your email.',
            'email' => $user->email
        ]);
    }

    private function generateAndSendPasswordResetOtp($user)
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        UserOtp::where('user_uuid', $user->uuid)->delete();
        UserOtp::create([
            'otp' => $otp,
            'user_uuid' => $user->uuid,
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0
        ]);

        Mail::to($user->email)->send(new ResetPasswordOtpMail($user, $otp));
    }

    /**
     * Verify the password reset OTP.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @unauthenticated
     */
    public function verifyPasswordResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();
        $userOtp = $user->otp()->latest()->first();

        if (!$userOtp) {
            return response()->json(['error' => 'No OTP request found'], 404);
        }

        if ($userOtp->expires_at < now()) {
            return response()->json(['error' => 'OTP has expired'], 400);
        }

        if ($userOtp->attempts >= 3) {
            return response()->json(['error' => 'Maximum attempts exceeded. Please request a new OTP.'], 400);
        }

        if (!$userOtp->verifyOtp($request->otp)) {
            $userOtp->increment('attempts');
            return response()->json(['error' => 'Invalid OTP'], 400);
        }

        // Store verification token in cache for 10 minutes
        $resetToken = Str::random(64);
        Cache::put("password_reset_token:{$user->uuid}", $resetToken, now()->addMinutes(10));

        return response()->json([
            'status' => 'success',
            'message' => 'OTP verified successfully.',
            'reset_token' => $resetToken,
            'expires_in' => 600 // 10 minutes
        ]);
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
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'reset_token' => 'required|string',
            'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/'],
        ]);

        $user = User::where('email', $request->email)->first();
        $cacheKey = "password_reset_token:{$user->uuid}";
        $storedToken = Cache::get($cacheKey);

        if (!$storedToken || $storedToken !== $request->reset_token) {
            return response()->json(['error' => 'Invalid or expired reset token'], 400);
        }

        $user->update([
            'password' => bcrypt($request->password),
        ]);

        // Clean up OTP and cache
        $user->otp()->delete();
        Cache::forget($cacheKey);

        return response()->json([
            'status' => 'success',
            'message' => 'Password has been successfully reset.'
        ]);
    }
}
