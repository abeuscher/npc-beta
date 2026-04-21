<?php

namespace App\Filament\Pages\Concerns;

use Filament\Forms;
use Filament\Notifications\Notification;

trait InteractsWithSectionedSettings
{
    protected function sectionSaveAction(string $id, string $label): Forms\Components\Actions
    {
        return Forms\Components\Actions::make([
            Forms\Components\Actions\Action::make("save_section_{$id}")
                ->label("Save {$label}")
                ->action(fn () => $this->saveSection($id, $label)),
        ])->alignEnd()->fullWidth();
    }

    public function saveSection(string $id, string $label): void
    {
        $this->form->getState();
        $this->persistSection($id);
        Notification::make()
            ->title("{$label} saved")
            ->success()
            ->send();
    }

    abstract protected function persistSection(string $id): void;
}
