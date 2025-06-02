<?php

namespace App\Filament\Pages\Auth;

use App\Mail\AdminOtpMail;
use App\Models\AdminOtp;
use Carbon\Carbon;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Property;

class Login extends BaseLogin
{
    #[Property]
    public bool $showOtpForm = false;
    
    #[Property]
    public ?string $enteredOtp = null;
    
    #[Property]
    public ?string $email = null;
    
    #[Property]
    public ?string $password = null;
    
    #[Property]
    public ?bool $remember = false;
    
    #[Property]
    public ?int $adminId = null;

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    #[Computed]
    public function otpForm(): array
    {
        return [
            TextInput::make('enteredOtp')
                ->label('Enter OTP')
                ->required()
                ->numeric()
                ->length(6)
                ->placeholder('Enter the 6-digit OTP sent to your email'),
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $this->email = $data['email'];
        $this->password = $data['password'];
        $this->remember = $data['remember'] ?? false;

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();
        
        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();

            $this->throwFailureValidationException();
        }
        
        // Store the admin ID before logging out
        $this->adminId = $user->id;
        
        // Generate and send OTP
        $this->generateAndSendOtp($user);
        
        // Logout the user temporarily until OTP is verified
        Filament::auth()->logout();
        
        // Store values in session for the OTP verification page
        session([
            'admin_id' => $this->adminId,
            'admin_email' => $this->email,
        ]);
        
        // Force the OTP form to show
        $this->showOtpForm = true;
        
        // Force notification about the OTP form being shown
        Notification::make()
            ->title('OTP Verification Required')
            ->body('Please enter the OTP sent to your email to complete login.')
            ->warning()
            ->persistent()
            ->send();
        
        return null;
    }
    
    protected function generateAndSendOtp($admin): void
    {
        // Generate a random 6-digit OTP
        $otp = (string) random_int(100000, 999999);
        
        // For debugging - show the OTP in a notification
        Notification::make()
            ->title('Your OTP for testing')
            ->body('Use this OTP: ' . $otp)
            ->info()
            ->persistent()
            ->send();
        
        // Set expiry time (5 minutes from now)
        $expiresAt = Carbon::now()->addMinutes(5);
        
        // Store OTP in database
        AdminOtp::updateOrCreate(
            ['admin_id' => $admin->id],
            [
                'otp' => $otp,
                'expires_at' => $expiresAt,
                'attempts' => 0,
            ]
        );
        
        // Send OTP via email
        $this->sendOtpEmail($admin->email, $otp);
        
        Notification::make()
            ->title('OTP Sent')
            ->body('A one-time password has been sent to your email.')
            ->success()
            ->send();
    }
    
    protected function sendOtpEmail($email, $otp): void
    {
        $admin = \App\Models\Admin::where('email', $email)->first();
        Mail::to($email)->send(new AdminOtpMail($otp, $admin->name));
    }
    
    public function verifyOtp(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();
            return null;
        }
        
        if (!$this->adminId) {
            Notification::make()
                ->title('Error')
                ->body('Session expired. Please try logging in again.')
                ->danger()
                ->send();
                
            $this->showOtpForm = false;
            return null;
        }
        
        if (empty($this->enteredOtp)) {
            Notification::make()
                ->title('Error')
                ->body('Please enter the OTP sent to your email.')
                ->danger()
                ->send();
            return null;
        }
        
        $adminOtp = AdminOtp::where('admin_id', $this->adminId)->first();
        
        if (!$adminOtp) {
            Notification::make()
                ->title('Error')
                ->body('No OTP found. Please request a new one.')
                ->danger()
                ->send();
                
            $this->showOtpForm = false;
            return null;
        }
        
        // Increment attempt counter
        $adminOtp->increment('attempts');
        
        // Check if OTP is expired
        if ($adminOtp->isExpired()) {
            Notification::make()
                ->title('OTP Expired')
                ->body('Your OTP has expired. Please try logging in again.')
                ->danger()
                ->send();
                
            $this->showOtpForm = false;
            return null;
        }
        
        // Debug notification to show what's being checked
        Notification::make()
            ->title('Checking OTP')
            ->body('Entered: ' . $this->enteredOtp . ' | Stored: ' . $adminOtp->otp)
            ->info()
            ->send();
        
        // Check if OTP is correct
        if ($adminOtp->otp !== $this->enteredOtp) {
            Notification::make()
                ->title('Invalid OTP')
                ->body('The OTP you entered is incorrect.')
                ->danger()
                ->send();
                
            return null;
        }
        
        // OTP is valid, log in the user
        $admin = \App\Models\Admin::find($this->adminId);
        
        if (!$admin) {
            Notification::make()
                ->title('Error')
                ->body('Admin not found. Please try logging in again.')
                ->danger()
                ->send();
                
            $this->showOtpForm = false;
            return null;
        }
        
        // Login directly without password check since we already verified
        Filament::auth()->login($admin, $this->remember);
        
        $user = Filament::auth()->user();
        
        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();
            $this->showOtpForm = false;
            $this->throwFailureValidationException();
        }
        
        // Delete the used OTP
        $adminOtp->delete();
        
        session()->regenerate();
        
        return app(LoginResponse::class);
    }
    
    public function resendOtp(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();
            return;
        }
        
        if (!$this->adminId || !$this->email) {
            Notification::make()
                ->title('Error')
                ->body('Session expired. Please try logging in again.')
                ->danger()
                ->send();
                
            $this->showOtpForm = false;
            return;
        }
        
        $admin = \App\Models\Admin::find($this->adminId);
        
        if (!$admin) {
            Notification::make()
                ->title('Error')
                ->body('User not found. Please try logging in again.')
                ->danger()
                ->send();
                
            $this->showOtpForm = false;
            return;
        }
        
        // Generate and send a new OTP
        $this->generateAndSendOtp($admin);
    }
    
    public function toggleOtpForm(): void
    {
        $this->showOtpForm = !$this->showOtpForm;
        
        if ($this->showOtpForm) {
            Notification::make()
                ->title('OTP Form Shown')
                ->body('OTP form is now visible for testing.')
                ->success()
                ->send();
        }
    }
} 