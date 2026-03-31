<?php

namespace App\Traits;

use App\Forms\Fieldsets\CmsFormFields;
use App\Livewire\PageBuilder;
use App\Models\CustomFieldDef;
use Filament\Forms;

trait HasPageBuilderForm
{
    /**
     * Assemble the standard CMS edit form layout.
     *
     * @param  string  $type           Content type: 'page', 'post', or 'event'.
     * @param  string  $modelType      Model type string for CustomFieldDef::forModel().
     * @param  string  $tagType        Tag type key for TagSelect (page, post, event).
     * @param  array   $extraTitleFields  Additional fields appended inside the Page Name section
     *                                    (e.g. page type selector, system slug display).
     * @param  array   $uniqueSections    Additional sections below the top row (e.g. event location).
     * @param  bool    $withSeo           Include the full-width SEO section.
     * @param  callable|null $pageBuilderProps  Callable for page builder; null to omit.
     */
    public static function pageBuilderFormSchema(
        string $type,
        string $modelType,
        string $tagType,
        array $extraTitleFields = [],
        array $uniqueSections = [],
        bool $withSeo = true,
        ?callable $pageBuilderProps = null,
        ?Forms\Components\Section $templateSection = null,
    ): array {
        // ── Row 1: Page Name | [Templates] | Settings | Tags ─────────────
        $pageNameSection = CmsFormFields::pageName($type);

        // Append any extra fields (type selector, system slug display) to the Page Name section
        if (! empty($extraTitleFields)) {
            $pageNameSection = $pageNameSection->schema(array_merge(
                $pageNameSection->getChildComponents(),
                $extraTitleFields
            ));
        }

        if ($templateSection) {
            // 4-3-3-2 layout: Page Name | Templates | Settings | Tags
            $schema = [
                Forms\Components\Group::make([
                    $pageNameSection->columnSpan(4),
                    $templateSection->columnSpan(3),
                    CmsFormFields::settings($type)->columnSpan(3),
                    CmsFormFields::tags($tagType)->columnSpan(2),
                ])->columns(12)->columnSpanFull(),
            ];
        } else {
            // Original 3-column layout
            $schema = [
                Forms\Components\Group::make([
                    $pageNameSection->columnSpan(1),
                    CmsFormFields::settings($type)->columnSpan(1),
                    CmsFormFields::tags($tagType)->columnSpan(1),
                ])->columns(3)->columnSpanFull(),
            ];
        }

        // ── Unique sections (event location, meeting, registration, etc.) ─
        foreach ($uniqueSections as $section) {
            $schema[] = $section->columnSpanFull();
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

                    Forms\Components\FileUpload::make('og_image_path')
                        ->label('Open Graph image')
                        ->helperText('Image used for social sharing previews. Replaces current image on save.')
                        ->disk('public')
                        ->directory('og-images')
                        ->visibility('public')
                        ->image()
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('noindex')
                        ->label('Hide from search engines')
                        ->helperText('Adds a noindex meta tag. Use for thank-you pages, confirmation pages, etc.'),
                ])
                ->columns(1)
                ->collapsible()
                ->collapsed()
                ->columnSpanFull();
        }

        // ── Page Builder (full width, hidden on create) ───────────────────
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
