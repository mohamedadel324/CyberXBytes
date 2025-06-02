<x-filament::page>
    <div class="max-w-md mx-auto">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold">OTP Login</h1>
            <p class="text-gray-500">Enter your credentials and OTP to login</p>
        </div>
        
        <x-filament::card>
            <form wire:submit="submit">
                {{ $this->form }}
                
                <div class="mt-6">
                    <x-filament::button type="submit" class="w-full">
                        Login with OTP
                    </x-filament::button>
                </div>
            </form>
        </x-filament::card>
    </div>
</x-filament::page> 