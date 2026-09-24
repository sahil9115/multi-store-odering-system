<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('order_number')->label('Order #'),
                TextEntry::make('user.name')->label('Customer'),
                TextEntry::make('status')->badge(),
                TextEntry::make('subtotal')->money(),
                TextEntry::make('discount_type')->badge(),
                TextEntry::make('discount_amount')->money(),
                TextEntry::make('total')->money(),
                TextEntry::make('placed_at')->dateTime(),
                TextEntry::make('deliveryAddress.line1')->label('Delivery address'),
            ]);
    }
}
