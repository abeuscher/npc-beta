<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Forms\Components\PermissionTable;
use App\Models\Role;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
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
        return static::canAccess() && $record->name !== 'super_admin';
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
            'import_data' => [
                'label'       => 'Import Data',
                'description' => 'Grants access to the Import Contacts wizard and import progress page.',
            ],
            'review_imports' => [
                'label'       => 'Review Imports',
                'description' => 'Grants access to the Importer review queue to preview, approve, or roll back pending import sessions.',
            ],
            'edit_theme_scss' => [
                'label'       => 'Edit Theme SCSS',
                'description' => 'Grants access to the SCSS editor tab on the Site Theme page. Developer-level access only.',
            ],
            'view_any_form_submission' => [
                'label'       => 'View Form Submissions',
                'description' => 'Grants access to view submissions on the Forms resource.',
            ],
            'view_form_submission' => [
                'label'       => 'View Form Submission Detail',
                'description' => 'Grants access to open individual submission records.',
            ],
            'delete_form_submission' => [
                'label'       => 'Delete Form Submissions',
                'description' => 'Grants the ability to delete individual form submission records (e.g. for GDPR removal).',
            ],
            'manage_routing_prefixes' => [
                'label'       => 'Manage Routing Prefixes',
                'description' => 'Grants access to the Routing Prefixes section in General Settings — blog, events, and member portal URL prefixes.',
            ],
        ];
    }

    public static function permissionAreas(): array
    {
        return [
            'crm'     => ['contact', 'household', 'organization', 'membership', 'note', 'tag', 'event', 'mailing_list'],
            'finance' => ['donation', 'transaction', 'fund', 'campaign'],
            'cms'     => ['post', 'page', 'form', 'collection', 'collection_item', 'navigation_item', 'navigation_menu'],
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

                Tables\Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->hidden(fn (Role $record) => $record->name === 'super_admin')
                    ->modalHeading('Delete Role')
                    ->modalSubmitActionLabel('Confirm')
                    ->form(function (Role $record): array {
                        $count = $record->users()->count();

                        if ($count === 0) {
                            return [
                                Forms\Components\Placeholder::make('message')
                                    ->hiddenLabel()
                                    ->content('This role has no assigned users and can be safely deleted.'),
                            ];
                        }

                        $otherRoles = Role::where('id', '!=', $record->id)
                            ->whereNotIn('name', ['super_admin'])
                            ->orderBy('label')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (Role $r) => [$r->id => $r->display_label])
                            ->toArray();

                        return [
                            Forms\Components\Placeholder::make('warning')
                                ->hiddenLabel()
                                ->content("This role is assigned to {$count} user(s). Choose how to proceed:"),

                            Forms\Components\Radio::make('action_type')
                                ->label('Action')
                                ->options([
                                    'reassign' => 'Reassign affected users to another role, then delete this role',
                                    'delete'   => 'Delete this role — affected users will lose all access',
                                ])
                                ->required()
                                ->live(),

                            Forms\Components\Select::make('reassign_to')
                                ->label('Reassign users to')
                                ->options($otherRoles)
                                ->required(fn (Get $get) => $get('action_type') === 'reassign')
                                ->visible(fn (Get $get) => $get('action_type') === 'reassign'),

                            Forms\Components\TextInput::make('confirm_text')
                                ->label('Type DELETE to confirm removing access from all affected users')
                                ->visible(fn (Get $get) => $get('action_type') === 'delete')
                                ->rules([
                                    fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                        if ($get('action_type') === 'delete' && $value !== 'DELETE') {
                                            $fail('You must type DELETE to confirm.');
                                        }
                                    },
                                ]),
                        ];
                    })
                    ->action(function (Role $record, array $data): void {
                        abort_unless(auth()->user()?->isSuperAdmin(), 403);

                        $count = $record->users()->count();

                        if ($count === 0) {
                            $record->delete();
                            return;
                        }

                        if (($data['action_type'] ?? null) === 'reassign') {
                            $newRole = Role::find($data['reassign_to']);
                            $record->users()->get()->each(function ($user) use ($record, $newRole) {
                                $user->removeRole($record);
                                $user->assignRole($newRole);
                            });
                        }

                        $record->delete();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            abort_unless(auth()->user()?->isSuperAdmin(), 403);

                            $blocked = $records->filter(fn (Role $r) => $r->users()->count() > 0);

                            if ($blocked->isNotEmpty()) {
                                $names = $blocked->map(fn (Role $r) => $r->display_label)->join(', ');
                                Notification::make()
                                    ->danger()
                                    ->title('Cannot delete roles with assigned users')
                                    ->body("The following roles have active user assignments: {$names}. Reassign users before deleting.")
                                    ->persistent()
                                    ->send();
                                return;
                            }

                            $records->each->delete();
                        }),
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
