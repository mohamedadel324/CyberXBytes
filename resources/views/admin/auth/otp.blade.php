<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    OTP Verification
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
                
                @if (session('message'))
                    <div class="bg-green-900/50 border border-green-500/50 text-green-100 px-4 py-3 rounded-lg mb-5">
                        {{ session('message') }}
                    </div>
                @endif
                
                @if (session('email_error'))
                    <div class="bg-red-900/50 border border-red-500/50 text-red-100 px-4 py-3 rounded-lg mb-5">
                        <strong>Email Error:</strong> {{ session('email_error') }}
                    </div>
                @endif
                
                <div class="mb-6 text-gray-300">
                    <p class="mb-1">A one-time password has been sent to your email.</p>
                    <p class="text-sm text-cyan-400">Please enter the 6-digit code to complete login.</p>
                </div>
                
                <form method="POST" action="{{ route('admin.otp.verify') }}">
                    @csrf
                    
                    <div class="mb-6">
                        <div class="relative">
                            <input type="text" name="otp" id="otp" class="w-full px-5 py-4 border-0 bg-dark-900 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-400 text-center text-xl tracking-widest" placeholder="000000" required autofocus maxlength="6">
                            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-cyan-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <button type="submit" class="w-full bg-gradient-to-r from-cyan-500 to-cyan-600 text-white font-bold py-4 px-4 rounded-lg hover:from-cyan-400 hover:to-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-dark-900 transition-all duration-300">
                            Verify OTP
                        </button>
                    </div>
                </form>
                
                <div class="flex items-center justify-between text-sm">
                    <a href="{{ route('admin.login') }}" class="text-gray-400 hover:text-white flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to login
                    </a>
                    <a href="{{ route('admin.otp.resend') }}" class="text-cyan-400 hover:text-cyan-300 hover:underline flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Resend code
                    </a>
                </div>
            </div>
        </div>
        
        <div class="mt-8 text-center text-gray-500 text-xs">
            &copy; {{ date('Y') }} CyberXbytes. All rights reserved.
        </div>
    </div>
</body>
</html> 