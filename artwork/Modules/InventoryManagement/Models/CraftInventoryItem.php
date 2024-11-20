<?php

namespace Artwork\Modules\InventoryManagement\Models;

use Artwork\Core\Database\Models\Model;
use Artwork\Modules\InventoryScheduling\Models\CraftInventoryItemEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $craft_inventory_group_id
 * @property int $order
 * @property CraftInventoryGroup $group
 * @property Collection $cells
 * @property Collection $events
 */
class CraftInventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'craft_inventory_group_id',
        'craft_inventory_group_folder_id',
        'order',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(
            CraftInventoryGroup::class,
            'craft_inventory_group_id',
            'id',
            'craft_inventory_categories'
        )->select(['id', 'craft_inventory_category_id', 'name', 'order']);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(
            CraftInventoryItemCell::class,
            'craft_inventory_item_id',
            'id'
        )
            ->select([
                'craft_inventory_item_cells.id', // Voll qualifizierter Spaltenname
                'craft_inventory_item_cells.crafts_inventory_column_id',
                'craft_inventory_item_cells.craft_inventory_item_id',
                'craft_inventory_item_cells.cell_value',
                'crafts_inventory_columns.order as column_order' // Optional: Falls `order` benötigt wird
            ])
            ->join(
                'crafts_inventory_columns',
                'crafts_inventory_columns.id',
                '=',
                'craft_inventory_item_cells.crafts_inventory_column_id'
            )
            ->orderBy('crafts_inventory_columns.order', 'asc');
    }


    public function events(): HasMany
    {
        return $this->hasMany(
            CraftInventoryItemEvent::class,
            'craft_inventory_item_id',
            'id'
        );
    }
}
