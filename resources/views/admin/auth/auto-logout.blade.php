<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging out...</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eefbfa',
                            100: '#d4f7f5',
                            200: '#aeefed',
                            300: '#75e3e2',
                            400: '#38ffe5', /* Primary color from Filament */
                            500: '#16b3b0',
                            600: '#0e9391',
                            700: '#107576',
                            800: '#125d5d',
                            900: '#134e4e',
                            950: '#042f2f',
                        },
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-900 text-white">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full bg-gray-800 rounded-lg shadow-lg overflow-hidden p-6 text-center">
            <h2 class="text-2xl font-bold mb-4">Logging out...</h2>
            <p class="text-gray-300 mb-4">Please wait while we log you out.</p>
            
            <div class="flex justify-center">
                <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary-400"></div>
            </div>
            
            <form id="logout-form" method="POST" action="{{ route('admin.logout') }}">
                @csrf
            </form>
            
            <script>
                // Auto-submit the form on page load
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        document.getElementById('logout-form').submit();
                    }, 500); // Small delay for visual feedback
                });
            </script>
        </div>
    </div>
</body>
</html> 