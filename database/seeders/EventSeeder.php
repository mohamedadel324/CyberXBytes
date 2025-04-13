<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $events = [
            [
                'title' => 'CyberX CTF 2023',
                'description' => 'Annual Capture The Flag competition for cybersecurity enthusiasts.',
                'background_image' => 'cyberx_ctf_2023_bg.jpg',
                'image' => 'cyberx_ctf_2023.jpg',
                'is_private' => false,
                'registration_start_date' => Carbon::now()->subDays(30),
                'registration_end_date' => Carbon::now()->addDays(7),
                'team_formation_start_date' => Carbon::now()->addDays(8),
                'team_formation_end_date' => Carbon::now()->addDays(14),
                'start_date' => Carbon::now()->addDays(15),
                'end_date' => Carbon::now()->addDays(17),
                'requires_team' => true,
                'team_minimum_members' => 2,
                'team_maximum_members' => 5,
            ],
            [
                'title' => 'Web Security Workshop',
                'description' => 'Hands-on workshop focusing on web application security.',
                'background_image' => 'web_security_workshop_bg.jpg',
                'image' => 'web_security_workshop.jpg',
                'is_private' => true,
                'registration_start_date' => Carbon::now()->subDays(15),
                'registration_end_date' => Carbon::now()->addDays(5),
                'team_formation_start_date' => Carbon::now()->addDays(6),
                'team_formation_end_date' => Carbon::now()->addDays(10),
                'start_date' => Carbon::now()->addDays(11),
                'end_date' => Carbon::now()->addDays(12),
                'requires_team' => false,
                'team_minimum_members' => 1,
                'team_maximum_members' => 1,
            ],
            [
                'title' => 'Cryptography Challenge',
                'description' => 'Test your cryptography skills with this challenging event.',
                'background_image' => 'crypto_challenge_bg.jpg',
                'image' => 'crypto_challenge.jpg',
                'is_private' => false,
                'registration_start_date' => Carbon::now()->addDays(5),
                'registration_end_date' => Carbon::now()->addDays(20),
                'team_formation_start_date' => Carbon::now()->addDays(21),
                'team_formation_end_date' => Carbon::now()->addDays(25),
                'start_date' => Carbon::now()->addDays(26),
                'end_date' => Carbon::now()->addDays(28),
                'requires_team' => true,
                'team_minimum_members' => 1,
                'team_maximum_members' => 3,
            ],
        ];
        
        foreach ($events as $event) {
            Event::create($event);
        }
    }
}
