<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationMenu extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'label',
        'handle',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(NavigationItem::class);
    }

    /**
     * Duplicate this menu and its items, giving the copy a unique handle and
     * remapping each item's parent_id self-reference onto the new rows so the
     * nested structure is preserved.
     */
    public function duplicate(): self
    {
        $copy = $this->replicate(['handle']);
        $copy->label = 'Copy of ' . $this->label;

        $base   = $this->handle . '-copy';
        $handle = $base;
        $i      = 2;
        while (static::where('handle', $handle)->exists()) {
            $handle = $base . '-' . $i++;
        }
        $copy->handle = $handle;
        $copy->save();

        $idMap = [];
        $items = $this->items()->get();
        foreach ($items as $item) {
            $newItem = $item->replicate(['navigation_menu_id', 'parent_id']);
            $newItem->navigation_menu_id = $copy->id;
            $newItem->parent_id          = null;
            $newItem->save();
            $idMap[$item->id] = $newItem->id;
        }
        foreach ($items as $item) {
            if ($item->parent_id !== null && isset($idMap[$item->parent_id])) {
                NavigationItem::whereKey($idMap[$item->id])
                    ->update(['parent_id' => $idMap[$item->parent_id]]);
            }
        }

        return $copy;
    }
}
