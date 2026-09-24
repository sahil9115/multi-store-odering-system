<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Models\StoreProduct;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStock extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low Stock')
            ->query(
                StoreProduct::query()
                    ->with(['store', 'product'])
                    ->whereNotNull('reorder_level')
                    ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
                    ->orderBy('quantity_on_hand')
            )
            ->paginated(false)
            ->emptyStateHeading('Nothing low on stock')
            ->columns([
                TextColumn::make('product.name')->label('Product'),
                TextColumn::make('store.name')->label('Store'),
                TextColumn::make('quantity_on_hand')->label('On hand')->badge()->color('danger'),
                TextColumn::make('reorder_level')->label('Reorder level'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Manage')
                    ->url(fn (StoreProduct $record) => ProductResource::getUrl('edit', ['record' => $record->product_id])),
            ]);
    }
}
