<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RoomRoomCategoryMapping extends Pivot
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'room_category_id'
    ];

    protected $table = 'room_room_category';

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function roomCategory(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class);
    }
}
