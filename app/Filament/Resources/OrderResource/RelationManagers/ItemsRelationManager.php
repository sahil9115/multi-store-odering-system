<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\OrderItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Order Items';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_name_snapshot')
            ->columns([
                TextColumn::make('product_name_snapshot')->label('Product'),
                TextColumn::make('quantity'),
                TextColumn::make('returned_quantity')
                    ->label('Returned')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(fn (int $state) => $state > 0 ? $state : '—'),
                TextColumn::make('line_total')->money(),
                TextColumn::make('fulfillment_status')
                    ->badge()
                    ->color(fn ($state) => match ($state->value) {
                        'allocated' => 'success',
                        'partial' => 'warning',
                        'failed' => 'danger',
                        'returned' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('allocations')
                    ->label('Fulfilled from')
                    ->state(fn (OrderItem $record) => $record->allocations
                        ->map(fn ($a) => "{$a->quantity_allocated} × {$a->store->name}".($a->returned_quantity > 0 ? " ({$a->returned_quantity} returned)" : ''))
                        ->implode(', ') ?: '—'),
            ]);
    }
}
