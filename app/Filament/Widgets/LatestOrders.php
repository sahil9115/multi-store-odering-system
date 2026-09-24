<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestOrders extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest Orders')
            ->query(Order::query()->latest('placed_at')->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('order_number')->label('Order #'),
                TextColumn::make('user.name')->label('Customer'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (OrderStatus $state) => match ($state) {
                        OrderStatus::Confirmed, OrderStatus::Fulfilled => 'success',
                        OrderStatus::Pending, OrderStatus::Processing => 'warning',
                        OrderStatus::Cancelled => 'danger',
                    }),
                TextColumn::make('total')->money(),
                TextColumn::make('placed_at')->dateTime()->label('Placed'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
