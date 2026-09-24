<?php

namespace App\Filament\Resources;

use App\Enums\StoreStatus;
use App\Filament\Resources\StoreResource\Pages;
use App\Models\Store;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Catalog';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                TextInput::make('address_line')
                    ->label('Address')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('lat')
                    ->label('Latitude')
                    ->required()
                    ->numeric()
                    ->minValue(-90)
                    ->maxValue(90),
                TextInput::make('lng')
                    ->label('Longitude')
                    ->required()
                    ->numeric()
                    ->minValue(-180)
                    ->maxValue(180),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(50),
                Select::make('status')
                    ->options([
                        StoreStatus::Active->value => 'Active',
                        StoreStatus::Inactive->value => 'Inactive',
                    ])
                    ->default(StoreStatus::Active->value)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->searchable()
                    ->badge(),
                TextColumn::make('address_line')
                    ->label('Address')
                    ->limit(40),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (StoreStatus $state) => $state === StoreStatus::Active ? 'success' : 'gray'),
                TextColumn::make('storeProducts_count')
                    ->label('Products stocked')
                    ->counts('storeProducts')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        StoreStatus::Active->value => 'Active',
                        StoreStatus::Inactive->value => 'Inactive',
                    ]),
                TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStores::route('/'),
            'create' => Pages\CreateStore::route('/create'),
            'edit' => Pages\EditStore::route('/{record}/edit'),
        ];
    }
}
