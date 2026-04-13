<?php

namespace App\Widgets\DonationForm;

use App\Widgets\Contracts\WidgetDefinition;

class DonationFormDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'donation_form';
    }

    public function label(): string
    {
        return 'Donation Form';
    }

    public function description(): string
    {
        return 'Configurable donation form with preset amounts, monthly, and annual options.';
    }

    public function category(): array
    {
        return ['giving_and_sales', 'forms'];
    }

    public function schema(): array
    {
        return [
            ['key' => 'heading',       'type' => 'text',   'label' => 'Heading', 'group' => 'content', 'subtype' => 'title'],
            ['key' => 'amounts',       'type' => 'text',   'label' => 'Preset amounts (comma-separated, e.g. 10,25,50,100)', 'group' => 'content'],
            ['key' => 'show_monthly',  'type' => 'toggle', 'label' => 'Show Monthly option', 'group' => 'appearance'],
            ['key' => 'show_annual',   'type' => 'toggle', 'label' => 'Show Annual option', 'group' => 'appearance'],
            ['key' => 'success_page',  'type' => 'text',   'label' => 'Success page slug (optional — leave blank to stay on this page)', 'group' => 'content', 'subtype' => 'url'],
        ];
    }

    public function defaults(): array
    {
        return [
            'heading'      => '',
            'amounts'      => '',
            'show_monthly' => false,
            'show_annual'  => false,
            'success_page' => '',
        ];
    }
}
