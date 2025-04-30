<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\ChallangeCategory;
use App\Models\Challange;
use App\Models\ChallangeFlag;
use App\Models\Submission;
use App\Models\Lab;
use App\Models\LabCategory;
use Illuminate\Support\Facades\DB;

class TestSubmissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clean up existing test data
        $this->cleanupTestData();
        
        // Create a test lab
        $lab = Lab::create([
            'name' => 'Test Lab',
            'ar_name' => 'معمل اختبار',
            'description' => 'This is a test lab for seeding data',
            'ar_description' => 'هذا معمل اختبار لبذر البيانات',
        ]);
        
        // Create lab categories
        $labCategory1 = LabCategory::create([
            'lab_uuid' => $lab->uuid,
            'title' => 'Web Security Lab',
            'ar_title' => 'معمل أمن الويب',
            'image' => 'test_images/web.png',
        ]);
        
        $labCategory2 = LabCategory::create([
            'lab_uuid' => $lab->uuid,
            'title' => 'Cryptography Lab',
            'ar_title' => 'معمل التشفير',
            'image' => 'test_images/crypto.png',
        ]);
        
        $labCategory3 = LabCategory::create([
            'lab_uuid' => $lab->uuid,
            'title' => 'Binary Exploitation Lab',
            'ar_title' => 'معمل استغلال الثنائيات',
            'image' => 'test_images/binary.png',
        ]);
        
        // Create categories
        $category1 = ChallangeCategory::create([
            'name' => 'Web Security',
            'icon' => 'test_icons/web.png',
        ]);

        $category2 = ChallangeCategory::create([
            'name' => 'Cryptography',
            'icon' => 'test_icons/crypto.png',
        ]);

        $category3 = ChallangeCategory::create([
            'name' => 'Binary Exploitation',
            'icon' => 'test_icons/binary.png',
        ]);

        // Create test users
        $user1 = User::create([
            'email' => 'user1@example.com',
            'password' => Hash::make('password'),
            'user_name' => 'hacker1',
            'country' => 'Saudi Arabia',
            'time_zone' => 'Asia/Riyadh',
            'email_verified_at' => now(),
        ]);

        $user2 = User::create([
            'email' => 'user2@example.com',
            'password' => Hash::make('password'),
            'user_name' => 'hacker2',
            'country' => 'United Arab Emirates',
            'time_zone' => 'Asia/Dubai',
            'email_verified_at' => now(),
        ]);

        $user3 = User::create([
            'email' => 'user3@example.com',
            'password' => Hash::make('password'),
            'user_name' => 'hacker3',
            'country' => 'United States',
            'time_zone' => 'America/New_York',
            'email_verified_at' => now(),
        ]);

        // Create simple challenge (single flag)
        $simpleChallenge = Challange::create([
            'category_uuid' => $category1->uuid,
            'lab_category_uuid' => $labCategory1->uuid,
            'title' => 'Simple SQL Injection',
            'description' => 'Find and exploit the SQL injection vulnerability',
            'difficulty' => 'easy',
            'bytes' => 100,
            'firstBloodBytes' => 200,
            'flag' => 'FLAG{simple_challenge_solved}',
            'flag_type' => 'single',
            'keywords' => ['sql', 'injection', 'web'],
            'available' => true,
            'made_by' => 'Test Author',
        ]);

        // Create multiple_all challenge (all flags must be solved)
        $multipleAllChallenge = Challange::create([
            'category_uuid' => $category2->uuid,
            'lab_category_uuid' => $labCategory2->uuid,
            'title' => 'Multi-step Encryption',
            'description' => 'Decrypt all the messages',
            'difficulty' => 'medium',
            'bytes' => 200,
            'firstBloodBytes' => 400,
            'flag_type' => 'multiple_all',
            'keywords' => ['crypto', 'encryption', 'decryption'],
            'available' => true,
            'made_by' => 'Test Author',
        ]);

        // Create flags for multiple_all challenge
        $multiAllFlag1 = ChallangeFlag::create([
            'challange_id' => $multipleAllChallenge->id,
            'flag' => 'FLAG{multi_all_part1}',
            'name' => 'Part 1',
            'description' => 'First encryption layer',
        ]);

        $multiAllFlag2 = ChallangeFlag::create([
            'challange_id' => $multipleAllChallenge->id,
            'flag' => 'FLAG{multi_all_part2}',
            'name' => 'Part 2',
            'description' => 'Second encryption layer',
        ]);

        // Create multiple_individual challenge (each flag gives points)
        $multipleIndividualChallenge = Challange::create([
            'category_uuid' => $category3->uuid,
            'lab_category_uuid' => $labCategory3->uuid,
            'title' => 'Binary Analysis Challenges',
            'description' => 'Analyze the binary and find vulnerabilities',
            'difficulty' => 'hard',
            'bytes' => 0,
            'firstBloodBytes' => 0,
            'flag_type' => 'multiple_individual',
            'keywords' => ['binary', 'exploitation', 'reverse engineering'],
            'available' => true,
            'made_by' => 'Test Author',
        ]);

        // Create flags for multiple_individual challenge
        $multiIndividualFlag1 = ChallangeFlag::create([
            'challange_id' => $multipleIndividualChallenge->id,
            'flag' => 'FLAG{multi_individual_buffer_overflow}',
            'bytes' => 150,
            'firstBloodBytes' => 300,
            'name' => 'Buffer Overflow',
            'description' => 'Find the buffer overflow vulnerability',
        ]);

        $multiIndividualFlag2 = ChallangeFlag::create([
            'challange_id' => $multipleIndividualChallenge->id,
            'flag' => 'FLAG{multi_individual_format_string}',
            'bytes' => 200,
            'firstBloodBytes' => 400,
            'name' => 'Format String',
            'description' => 'Exploit the format string vulnerability',
        ]);

        // Create submissions with different timings to simulate real activity

        // User 1 solves the simple challenge (first blood)
        Submission::create([
            'challange_uuid' => $simpleChallenge->uuid,
            'user_uuid' => $user1->uuid,
            'flag' => 'FLAG{simple_challenge_solved}',
            'solved' => true,
            'ip' => '192.168.1.1',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        // User 2 solves the simple challenge
        Submission::create([
            'challange_uuid' => $simpleChallenge->uuid,
            'user_uuid' => $user2->uuid,
            'flag' => 'FLAG{simple_challenge_solved}',
            'solved' => true,
            'ip' => '192.168.1.2',
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);

        // User 3 solves the simple challenge
        Submission::create([
            'challange_uuid' => $simpleChallenge->uuid,
            'user_uuid' => $user3->uuid,
            'flag' => 'FLAG{simple_challenge_solved}',
            'solved' => true,
            'ip' => '192.168.1.3',
            'created_at' => now()->subHours(12),
            'updated_at' => now()->subHours(12),
        ]);

        // User 2 solves the multiple_all challenge (first flag)
        Submission::create([
            'challange_uuid' => $multipleAllChallenge->uuid,
            'user_uuid' => $user2->uuid,
            'flag' => 'FLAG{multi_all_part1}',
            'solved' => true,
            'ip' => '192.168.1.2',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        // User 2 solves the multiple_all challenge (second flag, completing the challenge)
        Submission::create([
            'challange_uuid' => $multipleAllChallenge->uuid,
            'user_uuid' => $user2->uuid,
            'flag' => 'FLAG{multi_all_part2}',
            'solved' => true,
            'ip' => '192.168.1.2',
            'created_at' => now()->subDays(2)->addHours(3),
            'updated_at' => now()->subDays(2)->addHours(3),
        ]);

        // User 1 solves the multiple_all challenge (first flag)
        Submission::create([
            'challange_uuid' => $multipleAllChallenge->uuid,
            'user_uuid' => $user1->uuid,
            'flag' => 'FLAG{multi_all_part1}',
            'solved' => true,
            'ip' => '192.168.1.1',
            'created_at' => now()->subDays(1)->addHours(5),
            'updated_at' => now()->subDays(1)->addHours(5),
        ]);

        // User 1 solves the multiple_all challenge (second flag, completing the challenge)
        Submission::create([
            'challange_uuid' => $multipleAllChallenge->uuid,
            'user_uuid' => $user1->uuid,
            'flag' => 'FLAG{multi_all_part2}',
            'solved' => true,
            'ip' => '192.168.1.1',
            'created_at' => now()->subDays(1)->addHours(6),
            'updated_at' => now()->subDays(1)->addHours(6),
        ]);

        // User 1 solves the first multiple_individual flag (first blood)
        Submission::create([
            'challange_uuid' => $multipleIndividualChallenge->uuid,
            'user_uuid' => $user1->uuid,
            'flag' => 'FLAG{multi_individual_buffer_overflow}',
            'solved' => true,
            'ip' => '192.168.1.1',
            'created_at' => now()->subHours(8),
            'updated_at' => now()->subHours(8),
        ]);

        // User 3 solves the first multiple_individual flag
        Submission::create([
            'challange_uuid' => $multipleIndividualChallenge->uuid,
            'user_uuid' => $user3->uuid,
            'flag' => 'FLAG{multi_individual_buffer_overflow}',
            'solved' => true,
            'ip' => '192.168.1.3',
            'created_at' => now()->subHours(5),
            'updated_at' => now()->subHours(5),
        ]);

        // User 2 solves the second multiple_individual flag (first blood)
        Submission::create([
            'challange_uuid' => $multipleIndividualChallenge->uuid,
            'user_uuid' => $user2->uuid,
            'flag' => 'FLAG{multi_individual_format_string}',
            'solved' => true,
            'ip' => '192.168.1.2',
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);

        // User 1 also solves the second multiple_individual flag
        Submission::create([
            'challange_uuid' => $multipleIndividualChallenge->uuid,
            'user_uuid' => $user1->uuid,
            'flag' => 'FLAG{multi_individual_format_string}',
            'solved' => true,
            'ip' => '192.168.1.1',
            'created_at' => now()->subHours(1),
            'updated_at' => now()->subHours(1),
        ]);
    }
    
    /**
     * Cleanup any existing test data.
     */
    private function cleanupTestData()
    {
        // Delete test submissions
        Submission::where('ip', 'like', '192.168.1.%')->delete();
        
        // Delete test users
        User::where('email', 'like', '%@example.com')->delete();
        
        // Get test challenge IDs first
        $challengeIds = Challange::where('title', 'like', 'Simple SQL Injection')
            ->orWhere('title', 'like', 'Multi-step Encryption')
            ->orWhere('title', 'like', 'Binary Analysis Challenges')
            ->pluck('id')
            ->toArray();
        
        // Delete challenge flags for these challenges
        if (!empty($challengeIds)) {
            ChallangeFlag::whereIn('challange_id', $challengeIds)->delete();
        }
        
        // Delete test challenges
        Challange::where('title', 'like', 'Simple SQL Injection')
            ->orWhere('title', 'like', 'Multi-step Encryption')
            ->orWhere('title', 'like', 'Binary Analysis Challenges')
            ->delete();
        
        // Delete test categories
        ChallangeCategory::where('name', 'Web Security')
            ->orWhere('name', 'Cryptography')
            ->orWhere('name', 'Binary Exploitation')
            ->delete();
            
        // Delete test lab categories
        $labCategories = LabCategory::where('title', 'like', '%Lab')
            ->pluck('uuid')
            ->toArray();
            
        LabCategory::where('title', 'like', '%Lab')->delete();
            
        // Delete test labs
        Lab::where('name', 'Test Lab')->delete();
    }
}
