<?php

namespace App\Traits;

use App\Forms\Components\TagSelect;
use App\Forms\Fieldsets\CmsFormFields;
use App\Livewire\PageBuilder;
use App\Models\CustomFieldDef;
use Filament\Forms;

trait HasPageBuilderForm
{
    /**
     * Return only the metadata form sections (header, images, custom fields, SEO).
     * Used by EditPageDetails / EditPostDetails which do not render the page builder.
     */
    public static function metadataFormSchema(
        string $type,
        string $modelType,
        string $tagType,
        array $extraTitleFields = [],
        array $uniqueSections = [],
        array $imageFields = [],
        bool $withSeo = true,
        ?Forms\Components\Component $templateField = null,
    ): array {
        // ── Unified header fieldset: two 12-col rows ──────────────────────
        $row1 = [
            CmsFormFields::titleField()->columnSpan(3),
        ];

        if ($templateField) {
            $row1[] = $templateField->columnSpan(3);
            $row1[] = CmsFormFields::authorField()->columnSpan(2);
        } else {
            $row1[] = CmsFormFields::authorField()->columnSpan(3);
            $row1[] = Forms\Components\Group::make([])->columnSpan(2);
        }

        $row1[] = CmsFormFields::statusField($type)->columnSpan(2);
        $row1[] = CmsFormFields::publishedAtField()->columnSpan(2);

        $row2 = [
            CmsFormFields::slugField($type)->columnSpan(3),
            TagSelect::select($tagType)->columnSpan(6),
            TagSelect::creator($tagType)->columnSpan(3),
        ];

        $headerChildren = [
            Forms\Components\Grid::make(12)->schema($row1),
            Forms\Components\Grid::make(12)->schema($row2),
        ];

        if (! empty($extraTitleFields)) {
            $headerChildren = array_merge($headerChildren, $extraTitleFields);
        }

        $schema = [
            Forms\Components\Section::make()
                ->schema($headerChildren)
                ->columnSpanFull(),
        ];

        // ── Unique sections (event location, meeting, registration, etc.) ─
        foreach ($uniqueSections as $section) {
            $schema[] = $section->columnSpanFull();
        }

        // ── Images (full width, collapsed) ────────────────────────────────
        if (! empty($imageFields)) {
            $schema[] = Forms\Components\Section::make('Images')
                ->schema($imageFields)
                ->columns(1)
                ->collapsible()
                ->collapsed()
                ->columnSpanFull();
        }

        // ── Custom Fields (full width, collapsed, hidden when none) ───────
        $schema[] = Forms\Components\Section::make('Custom Fields')
            ->schema(fn () => CustomFieldDef::forModel($modelType)->get()
                ->map(fn ($def) => $def->toFilamentFormComponent())
                ->toArray()
            )
            ->columns(2)
            ->collapsible()
            ->collapsed()
            ->hidden(fn () => CustomFieldDef::forModel($modelType)->doesntExist())
            ->columnSpanFull();

        // ── SEO (full width, collapsed, always visible) ───────────────────
        if ($withSeo) {
            $schema[] = Forms\Components\Section::make('SEO')
                ->schema([
                    Forms\Components\TextInput::make('meta_title')
                        ->maxLength(255)
                        ->placeholder(fn ($record) => $record?->title ?? '')
                        ->helperText('Defaults to page title if blank.'),

                    Forms\Components\Textarea::make('meta_description')
                        ->rows(3)
                        ->maxLength(160)
                        ->placeholder('Auto-extracted from page content if blank.'),

                    Forms\Components\Toggle::make('noindex')
                        ->label('Hide from search engines')
                        ->helperText('Adds a noindex meta tag. Use for thank-you pages, confirmation pages, etc.'),
                ])
                ->columns(1)
                ->collapsible()
                ->collapsed()
                ->columnSpanFull();
        }

        return $schema;
    }

    /**
     * Return only the page builder section (Livewire component, hidden on create).
     */
    public static function pageBuilderSection(callable $pageBuilderProps): array
    {
        return [
            Forms\Components\Section::make('Page Builder')
                ->description('Add and arrange content blocks for this page.')
                ->schema([
                    Forms\Components\Livewire::make(
                        PageBuilder::class,
                        fn ($record) => $record ? $pageBuilderProps($record) : []
                    )->columnSpanFull(),
                ])
                ->hidden(fn ($record) => $record === null)
                ->columnSpanFull(),
        ];
    }

    /**
     * Assemble the standard CMS edit form layout (metadata + page builder).
     * Kept for backward compatibility — EventResource uses this combined form.
     */
    public static function pageBuilderFormSchema(
        string $type,
        string $modelType,
        string $tagType,
        array $extraTitleFields = [],
        array $uniqueSections = [],
        array $imageFields = [],
        bool $withSeo = true,
        ?callable $pageBuilderProps = null,
        ?Forms\Components\Component $templateField = null,
    ): array {
        $schema = static::metadataFormSchema(
            type: $type,
            modelType: $modelType,
            tagType: $tagType,
            extraTitleFields: $extraTitleFields,
            uniqueSections: $uniqueSections,
            imageFields: $imageFields,
            withSeo: $withSeo,
            templateField: $templateField,
        );

        if ($pageBuilderProps !== null) {
            $schema = array_merge($schema, static::pageBuilderSection($pageBuilderProps));
        }

        return $schema;
    }
}
