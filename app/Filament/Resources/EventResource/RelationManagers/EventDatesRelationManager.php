<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\EventDate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class EventDatesRelationManager extends RelationManager
{
    protected static string $relationship = 'eventDates';

    protected static ?string $title = 'Dates';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DateTimePicker::make('starts_at')
                ->required()
                ->seconds(false),

            Forms\Components\DateTimePicker::make('ends_at')
                ->nullable()
                ->seconds(false)
                ->after('starts_at'),

            Forms\Components\Select::make('status')
                ->options([
                    'inherited'  => 'Inherit from event',
                    'draft'      => 'Draft',
                    'published'  => 'Published',
                    'cancelled'  => 'Cancelled',
                ])
                ->default('inherited')
                ->required(),

            Forms\Components\Textarea::make('notes')
                ->nullable()
                ->rows(3)
                ->columnSpanFull(),

            Forms\Components\Section::make('Location Override')
                ->description('Leave blank to use the event\'s location for this occurrence.')
                ->schema([
                    Forms\Components\TextInput::make('location_override.address_line_1')
                        ->label('Address Line 1')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('location_override.address_line_2')
                        ->label('Address Line 2')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('location_override.city')
                        ->label('City')
                        ->maxLength(100),

                    Forms\Components\TextInput::make('location_override.state')
                        ->label('State')
                        ->maxLength(100),

                    Forms\Components\TextInput::make('location_override.zip')
                        ->label('Zip')
                        ->maxLength(20),

                    Forms\Components\TextInput::make('location_override.map_url')
                        ->label('Map URL')
                        ->url()
                        ->maxLength(2048),

                    Forms\Components\TextInput::make('location_override.map_label')
                        ->label('Map Link Label')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('meeting_url_override')
                        ->label('Meeting URL Override')
                        ->url()
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('starts_at')
            ->columns([
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Date & Time')
                    ->dateTime('D, M j, Y g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Ends')
                    ->time('g:i A')
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => fn ($state) => in_array($state, ['inherited', 'draft']),
                        'success' => 'published',
                        'danger'  => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('registrations_count')
                    ->label('Registrations')
                    ->counts('registrations')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Action::make('generateDates')
                    ->label('Generate from Rule')
                    ->icon('heroicon-o-sparkles')
                    ->visible(fn () => $this->getOwnerRecord()->is_recurring
                        && $this->getOwnerRecord()->recurrence_type === 'rule')
                    ->form([
                        Forms\Components\Select::make('freq')
                            ->label('Recurrence Pattern')
                            ->options([
                                'daily'         => 'Every X days',
                                'business_days' => 'Every X business days',
                                'weekly'        => 'Every X weeks',
                                'monthly_day'   => 'Monthly — Nth weekday (e.g. first Monday)',
                                'monthly_date'  => 'Monthly — fixed date (e.g. 15th)',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('interval')
                            ->label('Interval')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),

                        Forms\Components\CheckboxList::make('days_of_week')
                            ->label('Days of Week')
                            ->options([
                                'monday'    => 'Monday',
                                'tuesday'   => 'Tuesday',
                                'wednesday' => 'Wednesday',
                                'thursday'  => 'Thursday',
                                'friday'    => 'Friday',
                                'saturday'  => 'Saturday',
                                'sunday'    => 'Sunday',
                            ])
                            ->columns(4)
                            ->visible(fn (Forms\Get $get) => $get('freq') === 'weekly'),

                        Forms\Components\Select::make('nth')
                            ->label('Which occurrence')
                            ->options([1 => 'First', 2 => 'Second', 3 => 'Third', 4 => 'Fourth', 5 => 'Last'])
                            ->visible(fn (Forms\Get $get) => $get('freq') === 'monthly_day'),

                        Forms\Components\Select::make('weekday')
                            ->label('Weekday')
                            ->options([
                                'monday'    => 'Monday',
                                'tuesday'   => 'Tuesday',
                                'wednesday' => 'Wednesday',
                                'thursday'  => 'Thursday',
                                'friday'    => 'Friday',
                            ])
                            ->visible(fn (Forms\Get $get) => $get('freq') === 'monthly_day'),

                        Forms\Components\TextInput::make('day_of_month')
                            ->label('Day of Month')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->visible(fn (Forms\Get $get) => $get('freq') === 'monthly_date'),

                        Forms\Components\TimePicker::make('start_time')
                            ->label('Start Time')
                            ->required()
                            ->seconds(false),

                        Forms\Components\TimePicker::make('end_time')
                            ->label('End Time (optional)')
                            ->nullable()
                            ->seconds(false),

                        Forms\Components\DatePicker::make('until')
                            ->label('Repeat Until')
                            ->nullable(),

                        Forms\Components\TextInput::make('count')
                            ->label('Max Occurrences (optional)')
                            ->numeric()
                            ->minValue(1)
                            ->nullable(),
                    ])
                    ->action(function (array $data) {
                        $event = $this->getOwnerRecord();
                        $dates = $event->generateDatesFromRule($data);

                        $rows = $dates->map(fn ($d) => [
                            'event_id'   => $event->id,
                            'starts_at'  => $d['starts_at'],
                            'ends_at'    => $d['ends_at'],
                            'status'     => 'inherited',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])->toArray();

                        EventDate::insert($rows);

                        Notification::make()
                            ->title("{$dates->count()} date(s) generated.")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('starts_at', 'asc');
    }
}
