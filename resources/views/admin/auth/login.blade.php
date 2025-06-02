<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        cyan: {
                            400: '#22d3ee',
                            500: '#06b6d4',
                            600: '#0891b2',
                            700: '#0e7490',
                        },
                        dark: {
                            800: '#1e293b',
                            900: '#0f172a',
                            950: '#020617',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-dark-950 text-white min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md px-4">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-white mb-2">CyberXbytes</h1>
            <div class="h-1 w-16 bg-gradient-to-r from-cyan-400 to-cyan-600 mx-auto rounded-full"></div>
        </div>
        
        <div class="bg-black rounded-2xl overflow-hidden shadow-lg">
            <div class="bg-gradient-to-r p-5">
                <h2 class="text-center text-white text-2xl font-bold flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Admin Login
                </h2>
            </div>
            
            <div class="p-6">
                @if ($errors->any())
                    <div class="bg-red-900/50 border border-red-500/50 text-red-100 px-4 py-3 rounded-lg mb-5">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                <form method="POST" action="{{ route('admin.login.submit') }}">
                    @csrf
                    
                    <div class="mb-5">
                        <label for="email" class="block text-gray-300 font-medium mb-2 text-sm">Email Address</label>
                        <div class="relative">
                            <input type="email" name="email" id="email" class="w-full pl-10 pr-4 py-3 border-0 bg-dark-900 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-400" value="{{ old('email') }}" required autofocus>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-cyan-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="password" class="block text-gray-300 font-medium mb-2 text-sm">Password</label>
                        <div class="relative">
                            <input type="password" name="password" id="password" class="w-full pl-10 pr-4 py-3 border-0 bg-dark-900 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-400" required>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-cyan-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <input type="checkbox" name="remember" id="remember" class="h-4 w-4 text-cyan-500 bg-dark-900 border-gray-600 rounded focus:ring-cyan-400 focus:ring-offset-dark-900">
                            <label for="remember" class="ml-2 text-sm text-gray-300">Remember me</label>
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" class="w-full bg-gradient-to-r from-cyan-500 to-cyan-600 text-white font-bold py-3 px-4 rounded-lg hover:from-cyan-400 hover:to-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-dark-900 transition-all duration-300">
                            Sign In
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="mt-8 text-center text-gray-500 text-xs">
            &copy; {{ date('Y') }} CyberXbytes. All rights reserved.
        </div>
    </div>
</body>
</html> 