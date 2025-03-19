<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\User;
class Users extends BaseWidget
{
    protected function getStats(): array
    {
        $allTime = $this->getStatForPeriod();
        $last7Days = $this->getStatForPeriod(7);
        $lastMonth = $this->getStatForPeriod(30);
        $lastYear = $this->getStatForPeriod(365);

        return [
            $this->createStat('Total Users', $allTime),
            $this->createStat('Last 7 Days', $last7Days),
            $this->createStat('Last Month', $lastMonth),
            $this->createStat('Last Year', $lastYear),
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

    protected function createStat(string $label, array $data): Stat
    {
        return Stat::make($label, $data['currentCount'])
            ->description(abs($data['percentageChange']) . '% ' . ($data['isDecrease'] ? 'decrease' : 'increase'))
            ->color($data['isDecrease'] ? 'danger' : 'success')
            ->chart($data['dailyCounts']->toArray())
            ->descriptionIcon($data['isDecrease'] ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up');
    }
}
