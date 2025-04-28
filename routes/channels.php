<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;

Broadcast::channel('online-users', function () {
    return true;
});

// You can add more channel definitions here if needed