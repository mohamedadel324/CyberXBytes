<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventChallange;
use App\Models\Challange;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EventChallangeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $events = Event::all();
        $challenges = Challange::all();
        
        if ($events->isEmpty()) {
            $this->command->info('No events found. Please run EventSeeder first.');
            return;
        }
        
        if ($challenges->isEmpty()) {
            $this->command->info('No challenges found. Please run ChallangeSeeder first.');
            return;
        }
        
        foreach ($events as $event) {
            // Select random challenges for each event
            $selectedChallenges = $challenges->random(rand(5, 10));
            
            foreach ($selectedChallenges as $challenge) {
                // Add some variation to the points and difficulty for event challenges
                $points = $challenge->bytes * rand(1, 3);
                $difficulties = ['easy', 'medium', 'hard'];
                $difficulty = $difficulties[array_rand($difficulties)];
                
                EventChallange::create([
                    'event_uuid' => $event->uuid,
                    'category_uuid' => $challenge->category_uuid,
                    'title' => $challenge->title,
                    'description' => $challenge->description,
                    'difficulty' => $difficulty,
                    'made_by' => $challenge->made_by,
                    'bytes' => $points,
                    'firstBloodBytes' => $points * 2,
                ]);
            }
        }
    }
}
