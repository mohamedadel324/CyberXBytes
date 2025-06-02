@php
    $hasLogo = filled($logo = config('filament.layout.logo'));
@endphp

<x-filament-panels::page.simple>
    <x-slot name="title">
        {{ __('filament-panels::pages/auth/login.title') }}
    </x-slot>

    <div class="flex items-center justify-center">
        @if ($hasLogo)
            <div class="mb-4 flex justify-center">
                <x-filament-panels::logo />
            </div>
        @endif
    </div>
    
    <!-- Debug button to toggle OTP form -->
    <div class="mb-4 flex justify-center">
        <button 
            wire:click="toggleOtpForm"
            type="button"
            class="px-6 py-3 bg-red-600 text-white rounded-md hover:bg-red-700 font-bold text-lg"
        >
            CLICK HERE TO SHOW OTP FORM
        </button>
    </div>

    <!-- Debug information -->
    <div class="mb-4 text-center">
        <p class="text-sm text-gray-500">OTP Form Status: {{ $showOtpForm ? 'VISIBLE' : 'HIDDEN' }}</p>
    </div>

    @if (! $showOtpForm)
        <x-filament-panels::form wire:submit="authenticate">
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </x-filament-panels::form>
    @else
        <div class="space-y-6 bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
            <div class="text-center">
                <h2 class="text-2xl font-bold tracking-tight">
                    {{ __('Verify OTP') }}
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('A one-time password has been sent to your email.') }}
                </p>
            </div>

            <form wire:submit.prevent="verifyOtp" class="space-y-4">
                <div>
                    <label for="enteredOtp" class="block text-sm font-medium leading-6 text-gray-700 dark:text-gray-300">
                        {{ __('Enter OTP') }}
                    </label>
                    <div class="mt-2">
                        <input
                            type="text"
                            id="enteredOtp"
                            name="enteredOtp"
                            wire:model.live="enteredOtp"
                            required
                            class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:bg-gray-700 dark:text-white dark:ring-gray-600 dark:placeholder-gray-400 dark:focus:ring-primary-500"
                            placeholder="Enter 6-digit OTP"
                        />
                    </div>
                </div>

                <div>
                    <button
                        type="submit"
                        class="flex w-full justify-center rounded-md bg-primary-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:bg-primary-500 dark:hover:bg-primary-400"
                    >
                        {{ __('Verify OTP') }}
                    </button>
                </div>
            </form>

            <div class="text-center">
                <button
                    wire:click="resendOtp"
                    type="button"
                    class="text-sm text-gray-600 hover:text-primary-500 dark:text-gray-400"
                >
                    {{ __('Didn\'t receive the code? Resend') }}
                </button>
            </div>
        </div>
    @endif
</x-filament-panels::page.simple> 