<?php

namespace App\Services\ImportExport\Import;

use App\Models\User;

/**
 * Resolves the author id to stamp on rows created during an import: the
 * authenticated user when there is one, else the lowest-id user on the install.
 * Throws when no users exist (a page/template/event can't be authored).
 * Shared by the page, template, and event importers.
 */
class BundleAuthorResolver
{
    public function resolve(): int
    {
        if (auth()->check()) {
            return (int) auth()->id();
        }

        $first = User::orderBy('id')->value('id');
        if (! $first) {
            throw new \RuntimeException('No users exist on this install — cannot import pages.');
        }

        return (int) $first;
    }
}
