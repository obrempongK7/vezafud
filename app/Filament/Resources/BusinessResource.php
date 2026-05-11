<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Business;

class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Platform';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Business Information')->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(200),
                Forms\Components\Select::make('business_type')->options([
                    'restaurant' => 'Restaurant', 'bar' => 'Bar', 'lounge' => 'Lounge',
                    'bakery' => 'Bakery', 'chef' => 'Chef', 'caterer' => 'Caterer',
                    'food_vendor' => 'Food Vendor', 'coffee_shop' => 'Coffee Shop',
                    'food_truck' => 'Food Truck',
                ])->required(),
                Forms\Components\Textarea::make('description')->rows(3),
                Forms\Components\Select::make('price_range')->options([
                    'budget' => 'Budget', 'moderate' => 'Moderate', 'expensive' => 'Expensive', 'luxury' => 'Luxury',
                ]),
            ])->columns(2),
            Forms\Components\Section::make('Status')->schema([
                Forms\Components\Select::make('verification_status')->options([
                    'unverified' => 'Unverified', 'pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected',
                ])->required(),
                Forms\Components\Toggle::make('is_active')->label('Active'),
                Forms\Components\Toggle::make('is_featured')->label('Featured'),
                Forms\Components\Toggle::make('delivery_available')->label('Delivery Available'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('business_type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('owner.username')->label('Owner')->searchable(),
                Tables\Columns\TextColumn::make('verification_status')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'verified' => 'success', 'pending' => 'warning',
                        'rejected' => 'danger', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('rating_avg')->sortable()->numeric(2),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\IconColumn::make('is_featured')->boolean(),
                Tables\Columns\TextColumn::make('order_count')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('business_type')->options(['restaurant' => 'Restaurant', 'bar' => 'Bar', 'bakery' => 'Bakery']),
                Tables\Filters\SelectFilter::make('verification_status')->options(['pending' => 'Pending', 'verified' => 'Verified']),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TernaryFilter::make('is_featured'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('verify')
                    ->label('Verify')->icon('heroicon-o-check-circle')->color('success')
                    ->action(fn(Business $b) => $b->update(['verification_status' => 'verified']))
                    ->visible(fn(Business $b) => $b->verification_status !== 'verified'),
                Tables\Actions\Action::make('feature')
                    ->label(fn(Business $b) => $b->is_featured ? 'Unfeature' : 'Feature')
                    ->icon('heroicon-o-star')
                    ->action(fn(Business $b) => $b->update(['is_featured' => !$b->is_featured])),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array { return []; }
    public static function getPages(): array
    {
        return [
            'index'  => \App\Filament\Resources\BusinessResource\Pages\ListBusinesses::route('/'),
            'create' => \App\Filament\Resources\BusinessResource\Pages\CreateBusiness::route('/create'),
            'edit'   => \App\Filament\Resources\BusinessResource\Pages\EditBusiness::route('/{record}/edit'),
        ];
    }
}
