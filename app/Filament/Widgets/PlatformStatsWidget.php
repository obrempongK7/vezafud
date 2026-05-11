<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\User;
use App\Models\Business;
use App\Models\Order;
use App\Models\DeliveryProfile;
use App\Models\Report;

class PlatformStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $todayRevenue = Order::whereDate('created_at', today())->where('payment_status', 'paid')->sum('total');
        $monthRevenue = Order::whereMonth('created_at', now()->month)->where('payment_status', 'paid')->sum('total');

        return [
            Stat::make('Total Users', number_format(User::count()))
                ->description('+' . User::whereDate('created_at', today())->count() . ' today')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart(User::selectRaw('COUNT(*) as c')->whereBetween('created_at', [now()->subDays(7), now()])->groupBy(\DB::raw('DATE(created_at)'))->pluck('c')->toArray()),

            Stat::make('Active Businesses', number_format(Business::where('is_active', true)->count()))
                ->description(Business::where('verification_status', 'pending')->count() . ' pending verification')
                ->color('warning'),

            Stat::make('Orders Today', number_format(Order::whereDate('created_at', today())->count()))
                ->description('GH₵' . number_format($todayRevenue, 2) . ' revenue')
                ->color('primary'),

            Stat::make('Monthly Revenue', 'GH₵' . number_format($monthRevenue, 2))
                ->description('This month\'s commissions: GH₵' . number_format(Order::whereMonth('created_at', now()->month)->where('payment_status', 'paid')->sum('commission_amount'), 2))
                ->color('success'),

            Stat::make('Online Drivers', DeliveryProfile::where('availability_status', 'online')->count())
                ->description(DeliveryProfile::where('approval_status', 'pending')->count() . ' pending approval')
                ->color('info'),

            Stat::make('Open Reports', Report::where('status', 'pending')->count())
                ->description('Requiring moderation')
                ->color(Report::where('status', 'pending')->count() > 0 ? 'danger' : 'success'),
        ];
    }
}
