<?php

namespace Database\Seeders;

use App\Models\EventChallange;
use App\Models\EventChallangeFlag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EventChallangeFlagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $eventChallenges = EventChallange::all();
        
        if ($eventChallenges->isEmpty()) {
            $this->command->info('No event challenges found. Please run EventChallangeSeeder first.');
            return;
        }
        
        foreach ($eventChallenges as $challenge) {
            // Create 1-3 flags for each challenge
            $flagCount = rand(1, 3);
            
            for ($i = 1; $i <= $flagCount; $i++) {
                $points = $challenge->bytes / $flagCount;
                
                EventChallangeFlag::create([
                    'event_challange_id' => $challenge->id,
                    'flag' => "flag_{$challenge->id}_{$i}",
                    'bytes' => $points,
                    'firstBloodBytes' => $points * 2,
                    'order' => $i,
                ]);
            }
        }
    }
}
