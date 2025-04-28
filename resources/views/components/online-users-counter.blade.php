<div class="online-users-wrapper">
    <span class="online-users-label">{{ $label ?? 'Users Online:' }}</span>
    <span id="online-users-count" class="online-users-count">0</span>
</div>

<style>
    .online-users-wrapper {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background-color: #1a1a1a;
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        color: white;
        font-size: 0.875rem;
    }
    
    .online-users-count {
        font-weight: bold;
        color: #4ade80;
    }
</style>

@push('scripts')
    @vite('resources/js/online-users.js')
@endpush 