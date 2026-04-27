<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Concerns\HasRecordDetailSubNavigation;
use App\Filament\Resources\TemplateResource;
use App\Models\Page as PageModel;
use App\Models\Template;
use App\WidgetPrimitive\ViewRegistry;
use App\WidgetPrimitive\Views\RecordDetailView;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EditPageTemplateChrome extends Page
{
    use HasRecordDetailSubNavigation, InteractsWithRecord {
        HasRecordDetailSubNavigation::getSubNavigation insteadof InteractsWithRecord;
    }

    protected static string $resource = TemplateResource::class;

    protected static string $view = 'filament.resources.template-resource.pages.edit-page-template-chrome';

    public string $viewHandle = '';

    public ?RecordDetailView $resolvedView = null;

    public ?string $headerPageId = null;

    public ?string $footerPageId = null;

    public function mount(int | string $record, string $view): void
    {
        $this->record = $this->resolveRecord($record);
        $this->viewHandle = $view;

        $resolved = app(ViewRegistry::class)->findByHandle($this->record::class, $view);

        if ($resolved === null || ! in_array($view, ['page_template_header', 'page_template_footer'], true)) {
            throw (new ModelNotFoundException)->setModel(RecordDetailView::class, [$view]);
        }

        $this->resolvedView = $resolved;
        $this->headerPageId = $this->record->resolved('header_page_id');
        $this->footerPageId = $this->record->resolved('footer_page_id');
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('edit_site_chrome') ?? false;
    }

    public function getTitle(): string
    {
        return $this->resolvedView?->label ?? '';
    }

    public function getBreadcrumb(): ?string
    {
        return $this->resolvedView?->label;
    }

    public function getBreadcrumbs(): array
    {
        return [
            TemplateResource::getUrl() => 'Templates',
            EditPageTemplate::getUrl(['record' => $this->record]) => 'Edit Page Template',
            $this->resolvedView?->label ?? '',
        ];
    }

    public function getIsHeaderProperty(): bool
    {
        return $this->viewHandle === 'page_template_header';
    }

    public function getChromePositionProperty(): string
    {
        return $this->isHeader ? 'header' : 'footer';
    }

    public function getActiveChromePageIdProperty(): ?string
    {
        return $this->isHeader ? $this->headerPageId : $this->footerPageId;
    }

    public function getIsNonDefaultProperty(): bool
    {
        return ! $this->record->is_default;
    }

    public function getDefaultTemplateProperty(): ?Template
    {
        return Template::page()->where('is_default', true)->first();
    }

    public function getHasCustomChromeProperty(): bool
    {
        if ($this->record->is_default) {
            return true;
        }

        return $this->isHeader
            ? $this->record->header_page_id !== null
            : $this->record->footer_page_id !== null;
    }

    private function assertCanEdit(): void
    {
        abort_unless(auth()->user()?->can('edit_site_chrome'), 403);
    }

    public function enableCustomChrome(): void
    {
        $this->assertCanEdit();
        $position = $this->chromePosition;
        $page = $this->createChromePageFor($position);

        $this->record->update([$position . '_page_id' => $page->id]);

        if ($position === 'header') {
            $this->headerPageId = $page->id;
        } else {
            $this->footerPageId = $page->id;
        }

        Notification::make()->title('Custom ' . $position . ' created')->success()->send();
    }

    public function inheritChrome(): void
    {
        $this->assertCanEdit();
        $position = $this->chromePosition;

        $this->record->update([$position . '_page_id' => null]);

        $default = Template::page()->where('is_default', true)->first();

        if ($position === 'header') {
            $this->headerPageId = $default?->header_page_id;
        } else {
            $this->footerPageId = $default?->footer_page_id;
        }

        Notification::make()->title(ucfirst($position) . ' reset to inherit from default')->success()->send();
    }

    private function createChromePageFor(string $position): PageModel
    {
        $default = Template::page()->where('is_default', true)->first();
        $sourcePageId = $position === 'header' ? $default?->header_page_id : $default?->footer_page_id;

        return $this->record->createChromePage($position, $sourcePageId);
    }

    protected function subNavigationEntryPage(): ?string
    {
        return EditPageTemplate::class;
    }

    protected function additionalSubNavigationPages(): array
    {
        return [
            EditPageTemplateScss::class,
        ];
    }

    protected function recordDetailViewSubPageClass(): ?string
    {
        return EditPageTemplateChrome::class;
    }
}
