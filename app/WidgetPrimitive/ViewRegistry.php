<?php

namespace App\WidgetPrimitive;

use App\WidgetPrimitive\Views\RecordDetailView;
use Illuminate\Support\Collection;

class ViewRegistry
{
    /**
     * @return Collection<int, RecordDetailView>
     */
    public function forRecordType(string $fqcn): Collection
    {
        return RecordDetailView::query()
            ->where('record_type', $fqcn)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();
    }

    public function findByHandle(string $recordType, string $handle): ?RecordDetailView
    {
        return RecordDetailView::query()
            ->where('record_type', $recordType)
            ->where('handle', $handle)
            ->first();
    }
}
