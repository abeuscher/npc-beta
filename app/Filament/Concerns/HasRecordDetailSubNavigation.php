<?php

namespace App\Filament\Concerns;

use App\WidgetPrimitive\ViewRegistry;
use App\WidgetPrimitive\Views\RecordDetailView;
use Filament\Navigation\NavigationItem;

trait HasRecordDetailSubNavigation
{
    public function getSubNavigation(): array
    {
        $entryPage = $this->subNavigationEntryPage();
        $entryItems = $entryPage !== null && $entryPage::canAccess(['record' => $this->record])
            ? $entryPage::getNavigationItems(['record' => $this->record])
            : [];

        $pageClass = $this->recordDetailViewSubPageClass();
        $views = $pageClass !== null && $pageClass::canAccess(['record' => $this->record])
            ? app(ViewRegistry::class)->forRecordType($this->record::class)
            : collect();

        $additionalPages = array_filter(
            $this->additionalSubNavigationPages(),
            fn (string $class) => $class::canAccess(['record' => $this->record]),
        );

        $totalSubPages = count($entryItems) + $views->count() + count($additionalPages);

        if ($totalSubPages <= 1) {
            return [];
        }

        $items = $entryItems;

        foreach ($views as $view) {
            $items[] = $this->makeViewNavigationItem($view, $pageClass);
        }

        foreach ($additionalPages as $additionalPageClass) {
            $items = array_merge($items, $additionalPageClass::getNavigationItems(['record' => $this->record]));
        }

        return $items;
    }

    /**
     * The host's own page class — surfaced as the first sub-nav entry. Default
     * null (no self-listing). Override on the entry page so it lists itself
     * alongside the View-backed and Filament-form sub-pages.
     *
     * @return class-string|null
     */
    protected function subNavigationEntryPage(): ?string
    {
        return null;
    }

    /**
     * @return array<int, class-string>
     */
    protected function additionalSubNavigationPages(): array
    {
        return [];
    }

    /**
     * Concrete page class registered in the host Resource that renders a View
     * by handle. Required when the trait will surface View-backed sub-pages
     * (i.e., when `forRecordType` returns more than one View). Hosts whose
     * record type has at most one View can return null — sub-nav is skipped.
     */
    protected function recordDetailViewSubPageClass(): ?string
    {
        return null;
    }

    private function makeViewNavigationItem(RecordDetailView $view, string $pageClass): NavigationItem
    {
        $url = $pageClass::getUrl(['record' => $this->record, 'view' => $view->handle]);

        return NavigationItem::make($view->label)
            ->url($url)
            ->isActiveWhen(fn (): bool => request()->routeIs($pageClass::getRouteName())
                && request()->route('view') === $view->handle);
    }
}
