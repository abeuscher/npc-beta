<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewEventPage')
                ->label('View event page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(function () {
                    $record        = $this->getRecord();
                    $eventsPrefix  = config('site.events_prefix', 'events');
                    if ($record->landing_page_id && $record->landingPage) {
                        return url('/' . $record->landingPage->slug);
                    }
                    return url('/' . $eventsPrefix . '/' . $record->slug);
                })
                ->openUrlInNewTab(),

            Actions\Action::make('createLandingPage')
                ->label('Create basic landing page')
                ->icon('heroicon-o-document-plus')
                ->color('primary')
                ->visible(fn () => $this->getRecord()->landing_page_id === null)
                ->requiresConfirmation()
                ->modalHeading('Create landing page')
                ->modalDescription('This will create a new draft page with event widgets pre-configured. You can edit it fully after creation.')
                ->action(function () {
                    $event = $this->getRecord();

                    $page = Page::create([
                        'title'        => $event->title,
                        'is_published' => false,
                        'type'         => 'event',
                    ]);

                    // Override the auto-generated slug to include the events/ prefix.
                    // doNotGenerateSlugsOnUpdate() ensures this won't be regenerated.
                    $page->update(['slug' => 'events/' . $event->slug]);

                    $widgetHandles = ['event_description', 'event_dates', 'event_registration'];
                    $sort = 1;

                    foreach ($widgetHandles as $handle) {
                        $widgetType = WidgetType::where('handle', $handle)->first();

                        if (! $widgetType) {
                            continue;
                        }

                        PageWidget::create([
                            'page_id'        => $page->id,
                            'widget_type_id' => $widgetType->id,
                            'label'          => $widgetType->label,
                            'config'         => ['event_id' => $event->id],
                            'sort_order'     => $sort++,
                            'is_active'      => true,
                        ]);
                    }

                    $event->update(['landing_page_id' => $page->id]);

                    \Filament\Notifications\Notification::make()
                        ->title('Landing page created')
                        ->body('The page is saved as a draft. Edit it to customise before publishing.')
                        ->success()
                        ->send();

                    $this->redirect(
                        \App\Filament\Resources\PageResource::getUrl('edit', ['record' => $page])
                    );
                }),

            Actions\Action::make('editLandingPage')
                ->label('Edit landing page')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn () => $this->getRecord()->landing_page_id !== null)
                ->url(fn () => \App\Filament\Resources\PageResource::getUrl(
                    'edit', ['record' => $this->getRecord()->landing_page_id]
                )),

            Actions\DeleteAction::make(),
        ];
    }
}
