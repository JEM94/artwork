<?php

namespace Artwork\Modules\Shift\Models;

use App\Models\User;
use Artwork\Core\Database\Models\Pivot;
use Artwork\Modules\ShiftQualification\Models\ShiftQualification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftUser extends Pivot
{
    protected $table = 'shift_user';

    protected $fillable = [
        'shift_id',
        'user_id',
        'shift_qualification_id',
        'shift_count'
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shiftQualification(): BelongsTo
    {
        return $this->belongsTo(ShiftQualification::class);
    }

    public function scopeAllByShiftIdAndShiftQualificationId(
        Builder $builder,
        int $shiftId,
        int $shiftQualificationId
    ): Builder {
        return $builder
            ->where('shift_id', $shiftId)
            ->where('shift_qualification_id', $shiftQualificationId);
    }

    public function scopeByUserIdAndShiftId(Builder $builder, int $userId, int $shiftId): Builder
    {
        return $builder->where('user_id', $userId)->where('shift_id', $shiftId);
    }
}
