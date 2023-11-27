<?php
namespace Artwork\Modules\Room\Models;

use Antonrom\ModelChangesHistory\Traits\HasChangesHistory;
use Artwork\Core\Database\Models\Model;
use Artwork\Modules\Area\Models\Area;
use Artwork\Modules\Area\Models\BelongsToArea;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $order
 * @property string $temporary
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $area_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $deleted_at
 *
 * @property Area $area
 * @property \App\Models\User $creator
 * @property \Illuminate\Support\Collection<User> $room_admins
 * @property \Illuminate\Support\Collection<RoomFile> $room_files
 * @property \Illuminate\Support\Collection<Event> $events
 */
class Room extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Prunable;
    use HasChangesHistory;
    use BelongsToArea;

    protected $fillable = [
        'name',
        'description',
        'temporary',
        'start_date',
        'end_date',
        'area_id',
        'user_id',
        'order',
        'everyone_can_book'
    ];

    protected $with = [
      'admins'
    ];

    protected $casts = [
        'everyone_can_book' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'temporary' => 'boolean'
    ];

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id', 'users');
    }

    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class, 'room_user', 'room_id')
            ->withPivot('is_admin', 'can_request');
    }

    public function admins(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'room_user', 'room_id')
            ->wherePivot('is_admin', true);
    }

    public function room_files()
    {
        return $this->hasMany(RoomFile::class);
    }

    public function events()
    {
        return $this->hasMany(\App\Models\Event::class);
    }

    public function adjoining_rooms()
    {
        return $this->belongsToMany(Room::class, 'adjoining_room_main_room', 'main_room_id', 'adjoining_room_id');
    }

    public function main_rooms()
    {
        return $this->belongsToMany(Room::class, 'adjoining_room_main_room', 'adjoining_room_id', 'main_room_id');
    }

    public function categories()
    {
        return $this->belongsToMany(RoomCategory::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(RoomAttribute::class);
    }

    public function prunable()
    {
        return static::where('deleted_at', '<=', now()->subMonth())
            ->orWhere('temporary', true)
            ->where('end_date', '<=', now());
    }

    public function pruning() {
        return $this->room_files()->delete();
    }

    public function getEventsAt(Carbon $dateTime): Collection
    {
        return $this->events
            ->filter(fn (\App\Models\Event $event) => $dateTime->between(Carbon::parse($event->start_time), Carbon::parse($event->end_time)));
    }
}
