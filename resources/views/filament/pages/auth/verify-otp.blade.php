<x-filament::page>
    <div class="max-w-md mx-auto">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold">{{ $heading }}</h1>
            <p class="text-gray-500">{{ $subheading }}</p>
        </div>
        
        <x-filament::card>
            {{ $this->form }}
            
            <div class="flex flex-col gap-3 mt-6">
                @foreach ($this->getFormActions() as $action)
                    {{ $action }}
                @endforeach
            </div>
        </x-filament::card>
    </div>
</x-filament::page> 