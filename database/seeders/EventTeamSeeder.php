<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventTeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $events = Event::where('requires_team', true)->get();
        $teams = Team::all();
        $users = User::all();
        
        if ($events->isEmpty()) {
            $this->command->info('No team events found. Please run EventSeeder first.');
            return;
        }
        
        if ($teams->isEmpty()) {
            $this->command->info('No teams found. Please run TeamSeeder first.');
            return;
        }
        
        if ($users->isEmpty()) {
            $this->command->info('No users found. Please run UserTableSeeder first.');
            return;
        }
        
        foreach ($events as $event) {
            // Register random teams for each event
            $selectedTeams = $teams->random(rand(3, 6));
            
            foreach ($selectedTeams as $team) {
                $eventTeam = EventTeam::create([
                    'event_uuid' => $event->uuid,
                    'name' => $team->name,
                    'description' => $team->description,
                    'leader_uuid' => $team->leader_uuid,
                    'is_locked' => false,
                ]);
                
                // Add leader as member
                $eventTeam->members()->attach($team->leader_uuid, ['role' => 'leader']);
                
                // Add random team members (2-4 members including leader)
                $potentialMembers = $users->where('uuid', '!=', $team->leader_uuid);
                $selectedMembers = $potentialMembers->random(rand(1, 3));
                
                foreach ($selectedMembers as $member) {
                    $eventTeam->members()->attach($member->uuid, ['role' => 'member']);
                }
            }
        }
    }
}
