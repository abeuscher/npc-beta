<?php

namespace App\WidgetPrimitive\Slots;

use App\WidgetPrimitive\AmbientContexts\RecordDetailAmbientContext;
use App\WidgetPrimitive\Slot;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Database\Eloquent\Model;

final class RecordDetailSidebarSlot extends Slot
{
    public function handle(): string
    {
        return 'record_detail_sidebar';
    }

    public function label(): string
    {
        return 'Record Detail Sidebar';
    }

    public function ambientContext(Model $record): SlotContext
    {
        return new SlotContext(new RecordDetailAmbientContext($record), publicSurface: false);
    }

    public function layoutConstraints(): array
    {
        return [
            'allowed_appearance_fields' => ['background', 'text'],
            'dimensions'                => null,
            'column_stackable'          => true,
            'full_width_allowed'        => false,
        ];
    }

    public function configSurface(): ?string
    {
        return null;
    }
}
