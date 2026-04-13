<?php

namespace App\Widgets\PortalSignup;

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
}
