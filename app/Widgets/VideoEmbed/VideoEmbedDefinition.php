<?php

namespace App\Widgets\VideoEmbed;

use App\Widgets\Contracts\WidgetDefinition;

class VideoEmbedDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'video_embed';
    }

    public function label(): string
    {
        return 'Video Embed';
    }

    public function description(): string
    {
        return 'Responsive YouTube or Vimeo embed with privacy-friendly URLs.';
    }

    public function category(): array
    {
        return ['content', 'media'];
    }

    public function schema(): array
    {
        return [
            ['key' => 'video_url',       'type' => 'text',   'label' => 'Video URL',          'helper' => 'YouTube or Vimeo video URL', 'group' => 'content', 'subtype' => 'url'],
            ['key' => 'show_related',    'type' => 'toggle', 'label' => 'Show related videos', 'default' => false, 'helper' => 'Show related videos at the end (YouTube only)', 'group' => 'appearance'],
            ['key' => 'modest_branding', 'type' => 'toggle', 'label' => 'Reduce branding',    'default' => true,  'helper' => 'Reduce YouTube branding', 'group' => 'appearance'],
            ['key' => 'show_controls',   'type' => 'toggle', 'label' => 'Show controls',      'default' => true,  'helper' => 'Show player controls', 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'video_url'       => '',
            'show_related'    => false,
            'modest_branding' => true,
            'show_controls'   => true,
        ];
    }

    public function requiredConfig(): ?array
    {
        return ['keys' => ['video_url'], 'message' => 'Enter a YouTube or Vimeo URL.'];
    }
}
