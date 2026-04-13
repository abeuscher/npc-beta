<?php

namespace App\Widgets\SocialSharing;

use App\Widgets\Contracts\WidgetDefinition;

class SocialSharingDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'social_sharing';
    }

    public function label(): string
    {
        return 'Social Sharing';
    }

    public function description(): string
    {
        return 'Row of share buttons for social platforms, email, and link copying. No third-party scripts.';
    }

    public function category(): array
    {
        return ['content'];
    }

    public function assets(): array
    {
        return ['scss' => ['app/Widgets/SocialSharing/styles.scss']];
    }

    public function schema(): array
    {
        return [
            ['key' => 'heading',           'type' => 'text',       'label' => 'Heading', 'group' => 'content', 'subtype' => 'title'],
            ['key' => 'platforms',          'type' => 'checkboxes', 'label' => 'Platforms', 'columns' => 3, 'default' => ['bluesky', 'mastodon', 'email', 'copy_link', 'linkedin', 'facebook'], 'options' => [
                'bluesky'   => 'Bluesky',
                'mastodon'  => 'Mastodon',
                'email'     => 'Email',
                'copy_link' => 'Copy Link',
                'linkedin'  => 'LinkedIn',
                'facebook'  => 'Facebook',
            ], 'group' => 'content'],
            ['key' => 'alignment',         'type' => 'select',     'label' => 'Alignment',    'default' => 'center', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'group' => 'appearance'],
            ['key' => 'icon_size',          'type' => 'select',     'label' => 'Icon size',    'default' => 'small',  'options' => ['small' => 'Small (20px)', 'medium' => 'Medium (28px)'], 'group' => 'appearance'],
            ['key' => 'mastodon_instance',  'type' => 'text',       'label' => 'Mastodon instance domain', 'default' => 'mastodon.social', 'advanced' => true, 'group' => 'content', 'subtype' => 'url'],
        ];
    }

    public function defaults(): array
    {
        return [
            'heading'            => '',
            'platforms'          => ['bluesky', 'mastodon', 'email', 'copy_link', 'linkedin', 'facebook'],
            'alignment'          => 'center',
            'icon_size'          => 'small',
            'mastodon_instance'  => 'mastodon.social',
        ];
    }
}
