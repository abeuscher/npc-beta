<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Models\Campaign;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CampaignResource extends Resource
{
    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_campaign') ?? false;
    }

    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\TextInput::make('goal_amount')->numeric()->prefix('$')->nullable(),
                Forms\Components\DatePicker::make('starts_on'),
                Forms\Components\DatePicker::make('ends_on'),
                Forms\Components\Textarea::make('description')->rows(4)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('goal_amount')->money('USD')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\TextColumn::make('starts_on')->date()->sortable(),
                Tables\Columns\TextColumn::make('ends_on')->date()->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit'   => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }

    public static function exportColumnSpec(): array
    {
        return [
            ['key' => 'name',        'header' => 'name',        'value' => fn (Campaign $c) => $c->name],
            ['key' => 'description', 'header' => 'description', 'value' => fn (Campaign $c) => $c->description],
            ['key' => 'goal_amount', 'header' => 'goal_amount', 'value' => fn (Campaign $c) => $c->goal_amount,                       'type' => 'number'],
            ['key' => 'starts_on',   'header' => 'starts_on',   'value' => fn (Campaign $c) => $c->starts_on?->toDateString(),       'type' => 'date'],
            ['key' => 'ends_on',     'header' => 'ends_on',     'value' => fn (Campaign $c) => $c->ends_on?->toDateString(),         'type' => 'date'],
            ['key' => 'is_active',   'header' => 'is_active',   'value' => fn (Campaign $c) => (int) (bool) $c->is_active,           'type' => 'boolean'],
            ['key' => 'created_at',  'header' => 'created_at',  'value' => fn (Campaign $c) => $c->created_at?->toDateTimeString(),  'type' => 'datetime'],
        ];
    }
}
