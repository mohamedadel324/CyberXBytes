<?php

use Illuminate\Support\Facades\Broadcast;


Broadcast::channel('online-users', function () {
    return true;
});