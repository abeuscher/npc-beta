<?php

namespace App\Widgets\PortalSignup;

use App\WidgetPrimitive\DataContract;
use App\Widgets\Contracts\WidgetDefinition;

class PortalSignupDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'portal_signup';
    }

    public function label(): string
    {
        return 'Member Signup Form';
    }

    public function description(): string
    {
        return 'Registration form for new member portal accounts.';
    }

    public function category(): array
    {
        return ['portal', 'forms'];
    }

    public function schema(): array
    {
        return [];
    }

    public function defaults(): array
    {
        return [];
    }

    public function assets(): array
    {
        return ['js' => ['app/Widgets/PortalSignup/script.js']];
    }

    public function js(): ?string
    {
        return "(function () {
    var password     = document.getElementById('sw_password');
    var confirmation = document.getElementById('sw_password_confirmation');
    if (!password || !confirmation) return;
    var hint = document.createElement('span');
    hint.setAttribute('role', 'alert');
    hint.style.display = 'none';
    hint.textContent = 'Passwords do not match.';
    confirmation.parentNode.appendChild(hint);
    function check() {
        hint.style.display = (confirmation.value.length > 0 && password.value !== confirmation.value) ? '' : 'none';
    }
    password.addEventListener('input', check);
    confirmation.addEventListener('input', check);
}());";
    }

    public function dataContracts(array $config): array
    {
        return [
            'tiers' => new DataContract(
                version: '1.0.0',
                source: DataContract::SOURCE_SYSTEM_MODEL,
                fields: ['id', 'name', 'default_price', 'billing_interval'],
                model: 'membership_tier',
            ),
            'brand' => new DataContract(
                version: '1.0.0',
                source: DataContract::SOURCE_PAGE_CONTEXT,
                fields: ['site_name'],
            ),
        ];
    }
}
