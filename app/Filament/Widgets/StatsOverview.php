<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\StoreProduct;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $revenue = Order::query()
            ->whereIn('status', [OrderStatus::Confirmed, OrderStatus::Fulfilled])
            ->sum('total');

        $ordersToday = Order::query()->whereDate('placed_at', today())->count();

        $customerCount = User::query()->role(UserRole::Customer->value)->count();

        $lowStockCount = StoreProduct::query()
            ->whereNotNull('reorder_level')
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->count();

        return [
            Stat::make('Total Revenue', '$'.number_format((float) $revenue, 2))
                ->description('Confirmed & fulfilled orders')
                ->color('success'),

            Stat::make('Orders Today', $ordersToday)
                ->description('Placed since midnight')
                ->color('primary'),

            Stat::make('Customers', $customerCount)
                ->description('Registered customer accounts')
                ->color('gray'),

            Stat::make('Low Stock Alerts', $lowStockCount)
                ->description('Store items at or below reorder level')
                ->color($lowStockCount > 0 ? 'danger' : 'success'),
        ];
    }
}
