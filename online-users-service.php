<?php

// Autoload dependencies
require __DIR__.'/vendor/autoload.php';

// Bootstrap Laravel application to access the database
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Carbon\Carbon;

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Get count of online users (active in the last minute)
$onlineUsers = User::where('last_seen', '>=', Carbon::now()->subMinutes(1))->count();

// Return response as JSON
echo json_encode([
    'online_users' => $onlineUsers,
    'timestamp' => Carbon::now()->toIso8601String()
]); 