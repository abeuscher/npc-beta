<?php

namespace App\Widgets\QuickActions;

use App\Filament\Resources\ContactResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\FormResource;
use App\Filament\Resources\PostResource;
use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Source;

class QuickActionsDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'quick_actions';
    }

    public function label(): string
    {
        return 'Quick Actions';
    }

    public function description(): string
    {
        return 'Shortcut links to create new records in the admin panel.';
    }

    public function category(): array
    {
        return ['dashboard'];
    }

    public function allowedSlots(): array
    {
        return ['dashboard_grid'];
    }

    public function acceptedSources(): array
    {
        return [Source::HUMAN];
    }

    public function schema(): array
    {
        $options = [];
        foreach (static::actionRegistry() as $key => $entry) {
            $options[$key] = $entry['label'];
        }

        return [
            ['key' => 'actions', 'type' => 'checkboxes', 'label' => 'Actions', 'options' => $options, 'group' => 'content'],
        ];
    }

    public function defaults(): array
    {
        return [
            'actions' => ['new_contact', 'new_event', 'new_post'],
        ];
    }

    public function dataContract(array $config): ?DataContract
    {
        return null;
    }

    /**
     * Closed-set registry of admin shortcut actions. Every URL comes from an
     * internal Filament Resource::getUrl() call — no user-supplied URLs, no
     * string concatenation that could produce off-site destinations.
     *
     * @return array<string, array{label: string, url: \Closure, icon: string}>
     */
    public static function actionRegistry(): array
    {
        return [
            'new_contact' => ['label' => 'New Contact', 'url' => fn () => ContactResource::getUrl('create'), 'icon' => 'heroicon-o-user-plus'],
            'new_event'   => ['label' => 'New Event',   'url' => fn () => EventResource::getUrl('create'),   'icon' => 'heroicon-o-calendar-days'],
            'new_post'    => ['label' => 'New Post',    'url' => fn () => PostResource::getUrl('create'),    'icon' => 'heroicon-o-document-text'],
            'new_form'    => ['label' => 'New Form',    'url' => fn () => FormResource::getUrl('create'),    'icon' => 'heroicon-o-clipboard-document-list'],
        ];
    }
}
