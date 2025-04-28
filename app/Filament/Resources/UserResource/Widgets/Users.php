<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\User;
use App\Models\EventChallange;
use App\Models\EventChallangeSubmission;
use Illuminate\Support\Facades\DB;

class Users extends BaseWidget
{
    protected function getStats(): array
    {
        $userStats = $this->getUserStats();
        $challengeStats = $this->getChallengeStats();
        $byteStats = $this->getByteStats();
        $firstBloodStats = $this->getFirstBloodStats();

        return [
            $this->createUserStat('Total Users', $userStats['allTime']),
            $this->createChallengeStat('Challenges Solved', $challengeStats),
            $this->createByteStat('Total Bytes Earned', $byteStats),
            $this->createByteStat('First Blood Bytes', $firstBloodStats),
        ];
    }

    protected function getUserStats(): array
    {
        return [
            'allTime' => $this->getStatForPeriod(),
            'last7Days' => $this->getStatForPeriod(7),
        ];
    }

    protected function getChallengeStats(): array
    {
        $totalChallengesSolved = EventChallangeSubmission::where('solved', true)->count();
        $last7DaysSolved = EventChallangeSubmission::where('solved', true)
            ->where('solved_at', '>=', now()->subDays(7))
            ->count();
        
        $percentageChange = 0;
        $previous7DaysSolved = EventChallangeSubmission::where('solved', true)
            ->whereBetween('solved_at', [now()->subDays(14), now()->subDays(7)])
            ->count();
            
        if ($previous7DaysSolved > 0) {
            $percentageChange = (($last7DaysSolved - $previous7DaysSolved) / $previous7DaysSolved) * 100;
        }
        
        $isDecrease = $percentageChange < 0;
        
        $dailyCounts = collect(range(6, 0))->map(function ($daysAgo) {
            return EventChallangeSubmission::where('solved', true)
                ->whereDate('solved_at', now()->subDays($daysAgo))
                ->count();
        });
        
        return [
            'currentCount' => $totalChallengesSolved,
            'recentCount' => $last7DaysSolved,
            'percentageChange' => $percentageChange,
            'isDecrease' => $isDecrease,
            'dailyCounts' => $dailyCounts,
        ];
    }
    
    protected function getByteStats(): array
    {
        // Calculate regular bytes and first blood bytes
        $bytesData = $this->calculateBytesData();
        
        // Total bytes (regular + first blood)
        $totalBytes = $bytesData['regular']['total'] + $bytesData['firstBlood']['total'];
        $last7DaysBytes = $bytesData['regular']['last7Days'] + $bytesData['firstBlood']['last7Days'];
        $previous7DaysBytes = $bytesData['regular']['previous7Days'] + $bytesData['firstBlood']['previous7Days'];
        
        $percentageChange = 0;
        if ($previous7DaysBytes > 0) {
            $percentageChange = (($last7DaysBytes - $previous7DaysBytes) / $previous7DaysBytes) * 100;
        }
        
        $isDecrease = $percentageChange < 0;
        
        // Combine daily counts
        $dailyCounts = collect(range(6, 0))->map(function ($index) use ($bytesData) {
            return $bytesData['regular']['dailyCounts'][$index] + $bytesData['firstBlood']['dailyCounts'][$index];
        });
        
        return [
            'currentCount' => $totalBytes,
            'recentCount' => $last7DaysBytes,
            'percentageChange' => $percentageChange,
            'isDecrease' => $isDecrease,
            'dailyCounts' => $dailyCounts,
        ];
    }
    
    protected function getFirstBloodStats(): array
    {
        $bytesData = $this->calculateBytesData();
        
        $totalFirstBloodBytes = $bytesData['firstBlood']['total'];
        $last7DaysFirstBloodBytes = $bytesData['firstBlood']['last7Days'];
        $previous7DaysFirstBloodBytes = $bytesData['firstBlood']['previous7Days'];
        
        $percentageChange = 0;
        if ($previous7DaysFirstBloodBytes > 0) {
            $percentageChange = (($last7DaysFirstBloodBytes - $previous7DaysFirstBloodBytes) / $previous7DaysFirstBloodBytes) * 100;
        }
        
        $isDecrease = $percentageChange < 0;
        
        return [
            'currentCount' => $totalFirstBloodBytes,
            'recentCount' => $last7DaysFirstBloodBytes,
            'percentageChange' => $percentageChange,
            'isDecrease' => $isDecrease,
            'dailyCounts' => collect($bytesData['firstBlood']['dailyCounts']),
        ];
    }
    
    protected function calculateBytesData(): array
    {
        // Get all event challenges for reference
        $challenges = EventChallange::select('id', 'bytes', 'firstBloodBytes')->get()->keyBy('id');
        
        // Find first blood submissions (first submission per challenge)
        $firstBloods = EventChallangeSubmission::where('solved', true)
            ->select('event_challange_id', DB::raw('MIN(solved_at) as first_solved_at'))
            ->groupBy('event_challange_id')
            ->get()
            ->map(function ($item) {
                return [
                    'challenge_id' => $item->event_challange_id,
                    'solved_at' => $item->first_solved_at
                ];
            })
            ->keyBy('challenge_id');
        
        // Get all solved submissions
        $submissions = EventChallangeSubmission::where('solved', true)
            ->select('id', 'event_challange_id', 'user_uuid', 'solved_at')
            ->orderBy('solved_at')
            ->get();
        
        // Initialize data structure
        $regularBytesData = [
            'total' => 0,
            'last7Days' => 0,
            'previous7Days' => 0,
            'dailyCounts' => array_fill(0, 7, 0),
        ];
        
        $firstBloodBytesData = [
            'total' => 0,
            'last7Days' => 0,
            'previous7Days' => 0,
            'dailyCounts' => array_fill(0, 7, 0),
        ];
        
        // Process submissions
        foreach ($submissions as $submission) {
            $challengeId = $submission->event_challange_id;
            $solvedAt = $submission->solved_at;
            $isFirstBlood = false;
            
            // Check if this is a first blood submission
            if (isset($firstBloods[$challengeId]) && $solvedAt == $firstBloods[$challengeId]['solved_at']) {
                $isFirstBlood = true;
            }
            
            if (isset($challenges[$challengeId])) {
                $challenge = $challenges[$challengeId];
                
                // Regular bytes calculation
                $regularBytes = $challenge->bytes;
                $regularBytesData['total'] += $regularBytes;
                
                if ($solvedAt >= now()->subDays(7)) {
                    $regularBytesData['last7Days'] += $regularBytes;
                    
                    // Find which day in the last 7 days
                    for ($i = 0; $i < 7; $i++) {
                        if ($solvedAt->format('Y-m-d') == now()->subDays($i)->format('Y-m-d')) {
                            $regularBytesData['dailyCounts'][6 - $i] += $regularBytes;
                            break;
                        }
                    }
                } elseif ($solvedAt >= now()->subDays(14) && $solvedAt <= now()->subDays(7)) {
                    $regularBytesData['previous7Days'] += $regularBytes;
                }
                
                // First blood bytes calculation
                if ($isFirstBlood && $challenge->firstBloodBytes > 0) {
                    $firstBloodBytes = $challenge->firstBloodBytes;
                    $firstBloodBytesData['total'] += $firstBloodBytes;
                    
                    if ($solvedAt >= now()->subDays(7)) {
                        $firstBloodBytesData['last7Days'] += $firstBloodBytes;
                        
                        // Find which day in the last 7 days
                        for ($i = 0; $i < 7; $i++) {
                            if ($solvedAt->format('Y-m-d') == now()->subDays($i)->format('Y-m-d')) {
                                $firstBloodBytesData['dailyCounts'][6 - $i] += $firstBloodBytes;
                                break;
                            }
                        }
                    } elseif ($solvedAt >= now()->subDays(14) && $solvedAt <= now()->subDays(7)) {
                        $firstBloodBytesData['previous7Days'] += $firstBloodBytes;
                    }
                }
            }
        }
        
        return [
            'regular' => $regularBytesData,
            'firstBlood' => $firstBloodBytesData
        ];
    }

    protected function getStatForPeriod(int $days = null): array
    {
        $currentCount = User::when($days, fn($query) => $query->where('created_at', '>=', now()->subDays($days)))->count();
        $previousCount = User::when($days, function($query) use ($days) {
            $query->whereBetween('created_at', [now()->subDays($days * 2), now()->subDays($days)]);
        })->count();
        $percentageChange = (($currentCount - $previousCount) / ($previousCount ?: 1)) * 100;
        $isDecrease = $percentageChange < 0;

        $dailyCounts = collect(range(6, 0))->map(function ($daysAgo) use ($days) {
            return User::when($days, fn($query) => $query->where('created_at', '>=', now()->subDays($days)))
                ->whereDate('created_at', now()->subDays($daysAgo))
                ->count();
        });

        return [
            'currentCount' => $currentCount,
            'percentageChange' => $percentageChange,
            'isDecrease' => $isDecrease,
            'dailyCounts' => $dailyCounts,
        ];
    }

    protected function createUserStat(string $label, array $data): Stat
    {
        return Stat::make($label, $data['currentCount'])
            ->description(abs($data['percentageChange']) . '% ' . ($data['isDecrease'] ? 'decrease' : 'increase'))
            ->color($data['isDecrease'] ? 'danger' : 'success')
            ->chart($data['dailyCounts']->toArray())
            ->descriptionIcon($data['isDecrease'] ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up');
    }
    
    protected function createChallengeStat(string $label, array $data): Stat
    {
        return Stat::make($label, $data['currentCount'])
            ->description("Last 7 days: " . $data['recentCount'])
            ->chart($data['dailyCounts']->toArray())
            ->color($data['isDecrease'] ? 'danger' : 'success')
            ->descriptionIcon($data['isDecrease'] ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up');
    }
    
    protected function createByteStat(string $label, array $data): Stat
    {
        return Stat::make($label, $data['currentCount'])
            ->description("Last 7 days: " . $data['recentCount'])
            ->chart($data['dailyCounts']->toArray())
            ->color($data['isDecrease'] ? 'danger' : 'success')
            ->descriptionIcon($data['isDecrease'] ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up');
    }
}
