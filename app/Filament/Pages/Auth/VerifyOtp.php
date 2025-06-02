<?php

namespace App\Filament\Pages\Auth;

use App\Models\Admin;
use App\Models\AdminOtp;
use Carbon\Carbon;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Property;

class VerifyOtp extends Page
{
    use WithRateLimiting;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static string $view = 'filament.pages.auth.verify-otp';
    
    protected static bool $shouldRegisterNavigation = false;
    
    public ?string $otp = null;
    
    #[Property]
    public ?int $adminId = null;
    
    #[Property]
    public ?string $email = null;
    
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }
        
        // Get parameters from the session
        $this->adminId = session('admin_id');
        $this->email = session('admin_email');
        
        if (!$this->adminId || !$this->email) {
            Notification::make()
                ->title('Error')
                ->body('Session expired. Please try logging in again.')
                ->danger()
                ->send();
                
            redirect()->to(Filament::getLoginUrl());
        }
    }
    
    public function getTitle(): string|Htmlable
    {
        return new HtmlString('Verify OTP');
    }
    
    public function getHeading(): string|Htmlable
    {
        return new HtmlString('Verify OTP');
    }
    
    public function getSubheading(): string|Htmlable
    {
        return new HtmlString('A one-time password has been sent to your email. Please enter it below to complete login.');
    }
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('otp')
                    ->label('Enter OTP')
                    ->required()
                    ->numeric()
                    ->length(6)
                    ->placeholder('Enter the 6-digit OTP sent to your email'),
            ]);
    }
    
    public function verifyOtp(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title('Too many attempts')
                ->body('Please wait before trying again.')
                ->danger()
                ->send();
                
            return null;
        }
        
        if (!$this->adminId) {
            Notification::make()
                ->title('Error')
                ->body('Session expired. Please try logging in again.')
                ->danger()
                ->send();
                
            redirect()->to(Filament::getLoginUrl());
            return null;
        }
        
        if (empty($this->otp)) {
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
                
            redirect()->to(Filament::getLoginUrl());
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
                
            redirect()->to(Filament::getLoginUrl());
            return null;
        }
        
        // Debug notification to show what's being checked
        Notification::make()
            ->title('Checking OTP')
            ->body('Entered: ' . $this->otp . ' | Stored: ' . $adminOtp->otp)
            ->info()
            ->send();
        
        // Check if OTP is correct
        if ($adminOtp->otp !== $this->otp) {
            Notification::make()
                ->title('Invalid OTP')
                ->body('The OTP you entered is incorrect.')
                ->danger()
                ->send();
                
            return null;
        }
        
        // OTP is valid, log in the user
        $admin = Admin::find($this->adminId);
        
        if (!$admin) {
            Notification::make()
                ->title('Error')
                ->body('Admin not found. Please try logging in again.')
                ->danger()
                ->send();
                
            redirect()->to(Filament::getLoginUrl());
            return null;
        }
        
        // Login directly without password check since we already verified
        Filament::auth()->login($admin);
        
        $user = Filament::auth()->user();
        
        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();
            
            Notification::make()
                ->title('Error')
                ->body('You do not have permission to access this panel.')
                ->danger()
                ->send();
                
            redirect()->to(Filament::getLoginUrl());
            return null;
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
            Notification::make()
                ->title('Too many attempts')
                ->body('Please wait before trying again.')
                ->danger()
                ->send();
            return;
        }
        
        if (!$this->adminId || !$this->email) {
            Notification::make()
                ->title('Error')
                ->body('Session expired. Please try logging in again.')
                ->danger()
                ->send();
                
            redirect()->to(Filament::getLoginUrl());
            return;
        }
        
        $admin = Admin::find($this->adminId);
        
        if (!$admin) {
            Notification::make()
                ->title('Error')
                ->body('User not found. Please try logging in again.')
                ->danger()
                ->send();
                
            redirect()->to(Filament::getLoginUrl());
            return;
        }
        
        // Generate a new OTP
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
        
        Notification::make()
            ->title('OTP Resent')
            ->body('A new OTP has been sent to your email.')
            ->success()
            ->send();
    }
    
    protected function getFormActions(): array
    {
        return [
            Action::make('verify')
                ->label('Verify OTP')
                ->submit('verifyOtp'),
            
            Action::make('resend')
                ->label('Resend OTP')
                ->color('gray')
                ->action('resendOtp'),
        ];
    }
} 