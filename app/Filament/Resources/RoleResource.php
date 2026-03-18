<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Forms\Components\PermissionTable;
use App\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Roles';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->isSuperAdmin();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canAccess() && $record->name !== 'super_admin';
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canAccess() && ! in_array($record->name, ['super_admin', 'cms_editor']);
    }

    public static function canDeleteAny(): bool
    {
        return static::canAccess();
    }

    // ── Permission grouping ───────────────────────────────────────────────────

    public static function standalonePermissions(): array
    {
        return [
            'use_advanced_list_filters' => [
                'label'       => 'Use Advanced List Filters',
                'description' => 'Grants access to the raw SQL WHERE clause editor on mailing lists. Only assign to users who understand database queries.',
            ],
        ];
    }

    public static function permissionAreas(): array
    {
        return [
            'crm'     => ['contact', 'household', 'organization', 'membership', 'note', 'tag'],
            'finance' => ['donation', 'transaction', 'fund', 'campaign'],
            'cms'     => ['post', 'page', 'collection', 'collection_item', 'cms_tag', 'navigation_item'],
            'admin'   => ['user', 'widget_type'],
        ];
    }

    public static function permissionsForArea(array $resources): array
    {
        $actions = ['view_any', 'view', 'create', 'update', 'delete'];
        $options = [];
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                $perm           = "{$action}_{$resource}";
                $options[$perm] = str($action)->replace('_', ' ')->title()
                    . ' '
                    . str($resource)->replace('_', ' ')->title();
            }
        }
        return $options;
    }

    // ── Form ─────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        $areaLabels = [
            'crm'     => 'CRM',
            'finance' => 'Finance',
            'cms'     => 'CMS',
            'admin'   => 'Admin',
        ];

        $sections = [
            Forms\Components\Section::make('Role Details')->schema([
                Forms\Components\TextInput::make('label')
                    ->label('Label')
                    ->required()
                    ->maxLength(255)
                    ->live(debounce: 500)
                    ->afterStateUpdated(function (string $context, ?string $state, callable $set) {
                        if ($context === 'create' && $state) {
                            $set('name', str($state)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString());
                        }
                    })
                    ->helperText('Human-readable name shown in the UI, e.g. "Events Manager"'),

                Forms\Components\TextInput::make('name')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(Role::class, 'name', ignoreRecord: true)
                    ->regex('/^[a-z][a-z0-9_]*$/')
                    ->helperText('Machine identifier — letters, numbers, underscores only. Auto-filled from label on create.')
                    ->validationMessages([
                        'regex' => 'The slug may only contain lowercase letters, numbers, and underscores, and must start with a letter.',
                    ]),
            ])->columns(2),
        ];

        foreach ($areaLabels as $key => $label) {
            $resources  = static::permissionAreas()[$key];
            $sections[] = Forms\Components\Section::make("{$label} Permissions")
                ->schema([
                    PermissionTable::make("permissions_{$key}")
                        ->hiddenLabel()
                        ->resources($resources),
                ])
                ->collapsible();
        }

        $sections[] = Forms\Components\Section::make('Advanced')
            ->schema([
                Forms\Components\CheckboxList::make('permissions_advanced')
                    ->hiddenLabel()
                    ->options(
                        collect(static::standalonePermissions())
                            ->mapWithKeys(fn ($v, $k) => [$k => $v['label']])
                            ->toArray()
                    )
                    ->descriptions(
                        collect(static::standalonePermissions())
                            ->mapWithKeys(fn ($v, $k) => [$k => $v['description']])
                            ->toArray()
                    ),
            ])
            ->collapsible();

        return $form->schema($sections);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Role')
                    ->formatStateUsing(fn (?string $state, Role $record) => $state ?: $record->display_label)
                    ->sortable()
                    ->searchable()
                    ->description(fn (Role $record) => $record->name),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->sortable(),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->sortable(),
            ])
            ->defaultSort('label')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->disabled(fn (Role $record) => $record->name === 'super_admin'),

                Tables\Actions\DeleteAction::make()
                    ->modalDescription(function (Role $record) {
                        $count = $record->users()->count();
                        return $count > 0
                            ? "This role is assigned to {$count} user(s). They will immediately lose all permissions granted by this role."
                            : 'This role has no assigned users and can be safely deleted.';
                    })
                    ->disabled(fn (Role $record) => in_array($record->name, ['super_admin', 'cms_editor'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // ── Pages ─────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit'   => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
