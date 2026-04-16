<?php

namespace App\Http\Requests;

use App\Models\PageLayout;
use App\Models\PageWidget;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ReorderWidgetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('update_page') ?? false;
    }

    public function rules(): array
    {
        return [
            'items'                => 'required|array|min:1',
            'items.*.id'           => 'required|uuid',
            'items.*.type'         => 'nullable|string|in:widget,layout',
            'items.*.layout_id'    => 'nullable|uuid',
            'items.*.column_index' => 'nullable|integer|min:0',
            'items.*.sort_order'   => 'required|integer|min:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $owner = $this->route('owner');
            if (! $owner) {
                return;
            }
            $items = $this->normalizedItems();

            $widgetIds = collect($items)->where('type', 'widget')->pluck('id')->unique()->all();
            $layoutIds = collect($items)->where('type', 'layout')->pluck('id')->unique()->all();

            if (! empty($widgetIds)) {
                $count = PageWidget::forOwner($owner)->whereIn('id', $widgetIds)->count();
                if ($count !== count($widgetIds)) {
                    $v->errors()->add('items', 'Invalid widget IDs.');
                }
            }

            if (! empty($layoutIds)) {
                $count = PageLayout::forOwner($owner)->whereIn('id', $layoutIds)->count();
                if ($count !== count($layoutIds)) {
                    $v->errors()->add('items', 'Invalid layout IDs.');
                }
            }

            $referencedLayoutIds = collect($items)
                ->where('type', 'widget')
                ->pluck('layout_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (! empty($referencedLayoutIds)) {
                $count = PageLayout::forOwner($owner)->whereIn('id', $referencedLayoutIds)->count();
                if ($count !== count($referencedLayoutIds)) {
                    $v->errors()->add('items', 'Invalid referenced layout IDs.');
                }
            }
        });
    }

    /**
     * Items with `type` defaulted to 'widget' for backward compatibility with
     * the Phase 2 Vue store.
     */
    public function normalizedItems(): array
    {
        return array_map(
            fn ($item) => ['type' => $item['type'] ?? 'widget'] + $item,
            $this->validated()['items'] ?? $this->input('items', [])
        );
    }
}
