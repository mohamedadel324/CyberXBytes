<div>
    <form id="custom-logout-form" action="{{ route('admin.logout') }}" method="POST" style="display: none;">
        @csrf
    </form>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Override Filament's logout functionality
            if (typeof window.Livewire !== 'undefined') {
                document.addEventListener('click', function(e) {
                    // Check if the clicked element or its parent is a logout button
                    const button = e.target.closest('[x-on\\:click*="logout"]');
                    if (button) {
                        e.preventDefault();
                        e.stopPropagation();
                        document.getElementById('custom-logout-form').submit();
                    }
                }, true);
            }
            
            // Also find buttons by text content as a fallback
            const allButtons = document.querySelectorAll('button');
            allButtons.forEach(function(button) {
                if (button.textContent.includes('Sign out') || 
                    button.textContent.includes('Logout') || 
                    button.textContent.includes('Log out')) {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        document.getElementById('custom-logout-form').submit();
                    });
                }
            });
            
            // Also intercept any links to /admin/logout
            const allLinks = document.querySelectorAll('a');
            allLinks.forEach(function(link) {
                if (link.href.includes('/admin/logout')) {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        document.getElementById('custom-logout-form').submit();
                    });
                }
            });
        });
    </script>
</div> 