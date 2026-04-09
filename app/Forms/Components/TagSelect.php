<?php

namespace App\Forms\Components;

use App\Models\Tag;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;

class TagSelect
{
    public static function make(string $type): Group
    {
        return Group::make([
            static::select($type),
            static::creator($type),
        ])->columnSpanFull();
    }

    public static function select(string $type): Select
    {
        return Select::make('tags')
            ->label('Tags')
            ->multiple()
            ->relationship('tags', 'name', fn ($query) => $query->where('type', $type))
            ->searchable()
            ->preload()
            ->saveRelationshipsUsing(static function ($component, $record, $state) use ($type): void {
                $record->tags()->sync(array_values($state ?? []));
            });
    }

    public static function creator(string $type): TextInput
    {
        return TextInput::make('_new_tag')
            ->label('Create tag')
            ->placeholder('New tag label…')
            ->dehydrated(false)
            ->suffixAction(
                Action::make('add_tag')
                    ->icon('heroicon-o-plus')
                    ->action(static function (Get $get, Set $set) use ($type): void {
                        $name = trim($get('_new_tag') ?? '');

                        if (! filled($name)) {
                            return;
                        }

                        $tag     = Tag::firstOrCreate(['name' => $name, 'type' => $type]);
                        $current = array_map('strval', $get('tags') ?? []);

                        if (! in_array((string) $tag->id, $current, true)) {
                            $set('tags', [...$current, (string) $tag->id]);
                        }

                        $set('_new_tag', null);
                    })
            );
    }
}
