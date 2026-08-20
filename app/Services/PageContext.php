<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Page;

class PageContext
{
    public readonly ?Page $currentPage;
    public readonly mixed $currentUser;

    private array $formCache = [];

    public function __construct(?Page $currentPage = null)
    {
        $this->currentPage = $currentPage;
        // Composition safety (session 394): the portal guard is injected by
        // the MemberPortal plugin — on a portal-absent composition auth('portal')
        // throws on the undefined guard, so gate on the capability.
        $this->currentUser = app(\App\Plugins\CapabilityRegistry::class)->present('member-portal')
            ? auth('portal')->user()
            : null;
    }

    public function form(string $handle): ?Form
    {
        if (! array_key_exists($handle, $this->formCache)) {
            $this->formCache[$handle] = Form::where('handle', $handle)
                ->where('is_active', true)
                ->first();
        }

        return $this->formCache[$handle];
    }
}
