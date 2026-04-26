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
        $this->currentUser = auth('portal')->user();
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
