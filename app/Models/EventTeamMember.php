<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EventTeamMember extends Pivot
{
    use HasUuids;

    protected $table = 'event_team_members';
    
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
}
