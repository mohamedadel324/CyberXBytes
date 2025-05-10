<?php

namespace App\Policies;

use App\Models\Admin;

class AdminPolicy extends ResourcePolicy
{
    // Admins with admin permissions can manage other admins
} 