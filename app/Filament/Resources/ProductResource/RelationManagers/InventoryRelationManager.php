<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Enums\MovementType;
use App\Models\InventoryMovement;
use App\Models\StoreProduct;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Unique;

class InventoryRelationManager extends RelationManager
{
    protected static string $relationship = 'storeProducts';

    protected static ?string $title = 'Store Inventory';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('store_id')
                    ->relationship('store', 'name')
                    ->required()
                    ->disabledOn('edit')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('product_id', $this->getOwnerRecord()->id)),
                TextInput::make('quantity_on_hand')
                    ->label('Adjust to quantity')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                TextInput::make('reorder_level')
                    ->numeric()
                    ->minValue(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable(),
                TextColumn::make('quantity_on_hand')
                    ->label('On hand')
                    ->sortable(),
                TextColumn::make('reserved_quantity')
                    ->label('Reserved'),
                TextColumn::make('reorder_level')
                    ->label('Reorder level')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['reserved_quantity'] = 0;

                        return $data;
                    })
                    ->after(function (StoreProduct $record): void {
                        $this->recordInitialStock($record);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(function (StoreProduct $record, array $data): StoreProduct {
                        $this->applyQuantityAdjustment($record, (int) $data['quantity_on_hand']);
                        $record->update(['reorder_level' => $data['reorder_level'] ?? null]);

                        return $record;
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    protected function recordInitialStock(StoreProduct $record): void
    {
        if ($record->quantity_on_hand <= 0) {
            return;
        }

        InventoryMovement::create([
            'store_product_id' => $record->id,
            'type' => MovementType::Adjustment,
            'quantity_delta' => $record->quantity_on_hand,
            'balance_after' => $record->quantity_on_hand,
            'reason' => 'Initial stock',
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Guarded, concurrency-safe stock adjustment: locks the row, computes the delta
     * against the locked value (not the stale form value), and logs the movement.
     */
    protected function applyQuantityAdjustment(StoreProduct $record, int $newQuantity): void
    {
        DB::transaction(function () use ($record, $newQuantity) {
            $locked = StoreProduct::whereKey($record->id)->lockForUpdate()->first();

            $delta = $newQuantity - $locked->quantity_on_hand;

            if ($delta === 0) {
                return;
            }

            $locked->update(['quantity_on_hand' => $newQuantity]);

            InventoryMovement::create([
                'store_product_id' => $locked->id,
                'type' => MovementType::Adjustment,
                'quantity_delta' => $delta,
                'balance_after' => $newQuantity,
                'reason' => 'Manual admin adjustment',
                'created_by' => auth()->id(),
            ]);
        });
    }
}
