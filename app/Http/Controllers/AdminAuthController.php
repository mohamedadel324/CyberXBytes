<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\AdminOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AdminAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.auth.login');
    }
    
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        
        if (Auth::guard('admin')->attempt($credentials)) {
            $admin = Auth::guard('admin')->user();
            
            // Generate OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Store OTP
            AdminOtp::updateOrCreate(
                ['admin_id' => $admin->id],
                [
                    'otp' => $otp,
                    'expires_at' => Carbon::now()->addMinutes(5),
                    'attempts' => 0,
                ]
            );
            
            // Logout temporarily
            Auth::guard('admin')->logout();
            
            // Store admin ID in session
            session(['admin_id' => $admin->id]);
            
            // Send OTP email
            try {
                $this->sendOtpEmail($admin->email, $otp, $admin->name);
                
                // Log for debugging
                \Log::info('OTP email sent to: ' . $admin->email);
            } catch (\Exception $e) {
                // Log the error
                \Log::error('Failed to send OTP email: ' . $e->getMessage());
                
                // Store error in session
                session(['email_error' => $e->getMessage()]);
            }
            
            return redirect()->route('admin.otp.form');
        }
        
        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }
    
    /**
     * Send OTP email to the user
     */
    protected function sendOtpEmail($email, $otp, $name)
    {
        // Create email data
        $data = [
            'otp' => $otp,
            'name' => $name,
        ];
        
        // Send email using Laravel's Mail facade
        \Mail::send('emails.admin-otp', $data, function($message) use ($email) {
            $message->to($email)
                    ->subject('Your Admin Login OTP');
        });
    }
    
    public function showOtpForm()
    {
        $adminId = session('admin_id');
        
        if (!$adminId) {
            return redirect()->route('admin.login')->withErrors(['message' => 'Session expired. Please login again.']);
        }
        
        return view('admin.auth.otp');
    }
    
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);
        
        $adminId = session('admin_id');
        
        if (!$adminId) {
            return redirect()->route('admin.login')->withErrors(['message' => 'Session expired. Please login again.']);
        }
        
        $admin = Admin::find($adminId);
        $adminOtp = AdminOtp::where('admin_id', $adminId)->first();
        
        if (!$adminOtp) {
            return back()->withErrors(['otp' => 'No OTP found. Please login again.']);
        }
        
        if ($adminOtp->expires_at < now()) {
            $adminOtp->delete();
            return back()->withErrors(['otp' => 'OTP has expired. Please login again.']);
        }
        
        if ($adminOtp->otp !== $request->otp) {
            $adminOtp->increment('attempts');
            return back()->withErrors(['otp' => 'Invalid OTP. Please try again.']);
        }
        
        // OTP is valid, login the user
        Auth::guard('admin')->login($admin);
        
        // Delete the used OTP
        $adminOtp->delete();
        
        // Clear session data
        session()->forget(['admin_id']);
        
        return redirect('/admin');
    }
    
    public function resendOtp()
    {
        $adminId = session('admin_id');
        
        if (!$adminId) {
            return redirect()->route('admin.login')->withErrors(['message' => 'Session expired. Please login again.']);
        }
        
        $admin = Admin::find($adminId);
        
        if (!$admin) {
            return redirect()->route('admin.login')->withErrors(['message' => 'Admin not found. Please login again.']);
        }
        
        // Generate new OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP
        AdminOtp::updateOrCreate(
            ['admin_id' => $admin->id],
            [
                'otp' => $otp,
                'expires_at' => Carbon::now()->addMinutes(5),
                'attempts' => 0,
            ]
        );
        
        // Send OTP email
        try {
            $this->sendOtpEmail($admin->email, $otp, $admin->name);
            
            // Log for debugging
            \Log::info('OTP email resent to: ' . $admin->email);
            
            return redirect()->route('admin.otp.form')->with('message', 'New OTP has been sent to your email.');
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Failed to resend OTP email: ' . $e->getMessage());
            
            return redirect()->route('admin.otp.form')->withErrors(['message' => 'Failed to send OTP email: ' . $e->getMessage()]);
        }
    }
    
    public function logout()
    {
        Auth::guard('admin')->logout();
        session()->forget(['admin_id']);
        
        return redirect()->route('admin.login');
    }
} 