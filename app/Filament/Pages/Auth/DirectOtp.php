<?php

namespace App\Filament\Pages\Auth;

use App\Models\Admin;
use App\Models\AdminOtp;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Property;

class DirectOtp extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static string $view = 'filament.pages.auth.direct-otp';
    
    protected static bool $shouldRegisterNavigation = false;
    
    #[Property]
    public ?string $otp = null;
    
    #[Property]
    public ?string $email = null;
    
    #[Property]
    public ?string $password = null;
    
    public function mount(): void
    {
        $this->form->fill();
    }
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->label('Email'),
                TextInput::make('password')
                    ->password()
                    ->required()
                    ->label('Password'),
                TextInput::make('otp')
                    ->label('OTP Code')
                    ->required()
                    ->placeholder('Enter the 6-digit OTP'),
            ]);
    }
    
    public function submit(): void
    {
        $data = $this->form->getState();
        
        // First authenticate with email/password
        if (!Auth::guard('admin')->attempt([
            'email' => $data['email'],
            'password' => $data['password'],
        ])) {
            Notification::make()
                ->title('Invalid Credentials')
                ->body('The email or password is incorrect.')
                ->danger()
                ->send();
                
            return;
        }
        
        $admin = Auth::guard('admin')->user();
        
        // Check if OTP exists and is valid
        $adminOtp = AdminOtp::where('admin_id', $admin->id)->first();
        
        if (!$adminOtp) {
            // Generate a new OTP
            $otp = (string) random_int(100000, 999999);
            
            // Show OTP for testing
            Notification::make()
                ->title('New OTP Generated')
                ->body('Your OTP is: ' . $otp)
                ->info()
                ->persistent()
                ->send();
                
            // Store OTP
            AdminOtp::create([
                'admin_id' => $admin->id,
                'otp' => $otp,
                'expires_at' => now()->addMinutes(5),
                'attempts' => 0,
            ]);
            
            Notification::make()
                ->title('OTP Generated')
                ->body('Please enter the OTP shown in the notification.')
                ->warning()
                ->send();
                
            return;
        }
        
        // Verify OTP
        if ($adminOtp->otp !== $data['otp']) {
            Notification::make()
                ->title('Invalid OTP')
                ->body('The OTP you entered is incorrect.')
                ->danger()
                ->send();
                
            return;
        }
        
        // OTP is valid, complete login
        Auth::guard('admin')->login($admin);
        
        // Delete the used OTP
        $adminOtp->delete();
        
        Notification::make()
            ->title('Login Successful')
            ->body('You have been logged in successfully.')
            ->success()
            ->send();
            
        redirect()->to(Filament::getUrl());
    }
} 