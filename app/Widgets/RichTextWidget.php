<?php

namespace App\Widgets;

use Filament\Forms;

class RichTextWidget extends Widget
{
    public static function handle(): string
    {
        return 'rich_text';
    }

    public static function label(): string
    {
        return 'Rich Text';
    }

    public static function configSchema(): array
    {
        return [
            Forms\Components\RichEditor::make('config.content')
                ->label('Content')
                ->columnSpanFull(),
        ];
    }

    public function resolveData(array $config): array
    {
        return ['content' => $config['content'] ?? ''];
    }

    public function view(): string
    {
        return 'widgets.rich-text';
    }
}
