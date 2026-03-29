<?php

namespace App\Traits;

use App\Livewire\PageBuilder;
use App\Models\CustomFieldDef;
use Filament\Forms;

trait HasPageBuilderForm
{
    /**
     * Assemble the standard 3-column CMS edit form layout.
     *
     * @param  array  $titleSectionFields    Fields rendered inside the unnamed left-column section.
     * @param  array  $settingsSectionSchema  Fields rendered inside the Settings section (right, 2 cols).
     * @param  string $modelType             Model type string passed to CustomFieldDef::forModel().
     * @param  array  $uniqueSections        Additional form components appended to the left column below
     *                                       the title section (e.g. event location/meeting sections).
     * @param  bool   $withSeo               Whether to include the full-width SEO section (Row 3).
     * @param  callable|null $pageBuilderProps  Callable `fn($record) => ['pageId' => $record->id]`.
     *                                          When null the Page Builder section is omitted (Row 4).
     */
    public static function pageBuilderFormSchema(
        array $titleSectionFields,
        array $settingsSectionSchema,
        string $modelType,
        array $uniqueSections = [],
        bool $withSeo = true,
        ?callable $pageBuilderProps = null
    ): array {
        $schema = [
            // ── Row 1: Title section (1 col) + Settings section (2 cols) ──────
            Forms\Components\Group::make([
                Forms\Components\Group::make(array_merge(
                    [Forms\Components\Section::make()->schema($titleSectionFields)->columns(2)],
                    $uniqueSections
                ))->columnSpan(1),

                Forms\Components\Section::make('Settings')
                    ->schema($settingsSectionSchema)
                    ->columns(3)
                    ->columnSpan(2),
            ])->columns(3)->columnSpanFull(),

            // ── Row 2: Custom Fields (full width, collapsed, hidden when none) ─
            Forms\Components\Section::make('Custom Fields')
                ->schema(fn () => CustomFieldDef::forModel($modelType)->get()
                    ->map(fn ($def) => $def->toFilamentFormComponent())
                    ->toArray()
                )
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->hidden(fn () => CustomFieldDef::forModel($modelType)->doesntExist())
                ->columnSpanFull(),
        ];

        // ── Row 3: SEO (full width, collapsed, always visible) ────────────────
        if ($withSeo) {
            $schema[] = Forms\Components\Section::make('SEO')
                ->schema([
                    Forms\Components\TextInput::make('meta_title')
                        ->maxLength(255)
                        ->helperText('Defaults to page title if blank.'),

                    Forms\Components\Textarea::make('meta_description')
                        ->rows(3)
                        ->maxLength(160),
                ])
                ->columns(1)
                ->collapsible()
                ->collapsed()
                ->columnSpanFull();
        }

        // ── Row 4: Page Builder (full width, hidden on create) ────────────────
        if ($pageBuilderProps !== null) {
            $props = $pageBuilderProps;
            $schema[] = Forms\Components\Section::make('Page Builder')
                ->description('Add and arrange content blocks for this page.')
                ->schema([
                    Forms\Components\Livewire::make(
                        PageBuilder::class,
                        fn ($record) => $record ? $props($record) : []
                    )->columnSpanFull(),
                ])
                ->hidden(fn ($record) => $record === null)
                ->columnSpanFull();
        }

        return $schema;
    }
}
