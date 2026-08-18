<?php

namespace App\Widgets\PortalLogin;

use App\WidgetPrimitive\DataContract;
use App\Widgets\Contracts\WidgetDefinition;

class PortalLoginDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'portal_login';
    }

    public function label(): string
    {
        return 'Member Login Form';
    }

    public function description(): string
    {
        return 'Email and password login form for the member portal.';
    }

    public function category(): array
    {
        return ['portal', 'forms'];
    }

    public function schema(): array
    {
        return [];
    }
   public function demoAppearanceConfig(): array
    {
        return [
            'layout'     => [
                'padding' => [
                    'top'    => '100',
                    'left'   => '0',
                    'right'  => '0',
                    'bottom' => '75',
                ],
            ],
        ];
    }
    public function defaults(): array
    {
        return [];
    }

    public function dataContract(array $config): ?DataContract
    {
        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_PAGE_CONTEXT,
            fields: ['site_name'],
        );
    }
}
