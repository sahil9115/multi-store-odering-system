<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlatformDiscountResource\Pages;
use App\Models\PlatformDiscount;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlatformDiscountResource extends Resource
{
    protected static ?string $model = PlatformDiscount::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Discounts';

    protected static ?string $navigationLabel = 'Platform Discounts';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('min_order_amount')
                    ->label('Minimum order amount')
                    ->required()
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0),
                TextInput::make('discount_percent')
                    ->label('Discount %')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
                TextInput::make('priority')
                    ->numeric()
                    ->default(0)
                    ->helperText('Higher priority wins when more than one platform discount qualifies.'),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at'),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('min_order_amount')->money()->sortable(),
                TextColumn::make('discount_percent')->suffix('%')->sortable(),
                TextColumn::make('priority')->sortable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('starts_at')->dateTime()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ends_at')->dateTime()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformDiscounts::route('/'),
            'create' => Pages\CreatePlatformDiscount::route('/create'),
            'edit' => Pages\EditPlatformDiscount::route('/{record}/edit'),
        ];
    }
}
