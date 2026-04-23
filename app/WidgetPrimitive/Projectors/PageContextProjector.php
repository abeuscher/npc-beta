<?php

namespace App\WidgetPrimitive\Projectors;

use App\Models\Page;
use App\Services\PageContextTokens;
use App\WidgetPrimitive\DataContract;

final class PageContextProjector
{
    public function __construct(
        private readonly PageContextTokens $pageContextTokens,
    ) {}

    /**
     * Scalar-map DTO of page-context tokens.
     *
     * If the contract declares no fields, the full PageContextTokens::TOKENS
     * map is returned — SOURCE_PAGE_CONTEXT treats the source itself as the
     * capability boundary (the token set is bounded, public, and reviewed
     * as a single artifact). Contracts that declare explicit fields are still
     * honored for the narrower per-field case.
     *
     * @return array<string, string>
     */
    public function project(DataContract $contract, ?Page $page): array
    {
        $all = $page ? $this->pageContextTokens->values($page) : [];

        if ($contract->fields === []) {
            $dto = [];
            foreach (PageContextTokens::TOKENS as $field) {
                $dto[$field] = (string) ($all[$field] ?? '');
            }
            return $dto;
        }

        $dto = [];
        foreach ($contract->fields as $field) {
            $dto[$field] = (string) ($all[$field] ?? '');
        }
        return $dto;
    }
}
