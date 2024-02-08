<?php

namespace App\Models;

use Artwork\Modules\Shift\Models\Shift;
use Artwork\Modules\Shift\Models\ShiftServiceProvider;
use Artwork\Modules\ShiftQualification\Models\ServiceProviderShiftQualification;
use Artwork\Modules\ShiftQualification\Models\ShiftQualification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $profile_image
 * @property string $provider_name
 * @property string $work_name
 * @property string $work_description
 * @property string $email
 * @property string $phone_number
 * @property string $street
 * @property string $zip_code
 * @property string $location
 * @property string $note
 * @property int $salary_per_hour
 * @property string $salary_description
 * @property string $created_at
 * @property string $updated_at
 * @property int $can_work_shifts
 */
class ServiceProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_image',
        'provider_name',
        'email',
        'phone_number',
        'street',
        'zip_code',
        'location',
        'note',
        'salary_per_hour',
        'salary_description',
        'work_name',
        'work_description',
        'can_work_shifts'
    ];

    protected $with = ['contacts'];

    protected $appends = [
        'name',
        'type',
        'profile_photo_url',
        'assigned_craft_ids'
    ];

    protected $casts = [
        'can_work_shifts' => 'boolean'
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(ServiceProviderContacts::class);
    }

    public function shifts(): BelongsToMany
    {
        return $this
            ->belongsToMany(Shift::class, 'shifts_service_providers')
            ->using(ShiftServiceProvider::class);
    }

    public function assignedCrafts(): BelongsToMany
    {
        return $this->belongsToMany(Craft::class, 'service_provider_assigned_crafts');
    }

    public function shiftQualifications(): BelongsToMany
    {
        return $this
            ->belongsToMany(ShiftQualification::class, 'service_provider_shift_qualifications')
            ->using(ServiceProviderShiftQualification::class);
    }

    /**
     * @return array<int>
     */
    public function getAssignedCraftIdsAttribute(): array
    {
        return $this->assignedCrafts()->pluck('crafts.id')->toArray();
    }

    public function getShiftIdsBetweenStartDateAndEndDate(
        Carbon $startDate,
        Carbon $endDate
    ): \Illuminate\Support\Collection {
        return $this->shifts()->eventStartDayAndEventEndDayBetween($startDate, $endDate)->pluck('shifts.id');
    }

    public function getNameAttribute(): string
    {
        return $this->provider_name;
    }

    public function getShiftsAttribute(): Collection
    {
        return $this->shifts()
            ->without(['craft', 'users', 'event.project.shiftRelevantEventTypes'])
            ->with(['event.room'])
            ->get()
            ->makeHidden(['allUsers'])
            ->groupBy(function ($shift) {
                return $shift->event->days_of_event;
            });
    }

    public function getTypeAttribute(): string
    {
        return 'service_provider';
    }

    public function getProfilePhotoUrlAttribute(): string
    {
        return $this->profile_image ?
            $this->profile_image :
            'https://ui-avatars.com/api/?name=' . $this->provider_name[0] . '&color=7F9CF5&background=EBF4FF';
    }

    public function plannedWorkingHours($startDate, $endDate): float|int
    {
        $shiftsInDateRange = $this->shifts()
            ->whereBetween('event_start_day', [$startDate, $endDate])
            ->get();

        $plannedWorkingHours = 0;

        foreach ($shiftsInDateRange as $shift) {
            $shiftStart = Carbon::parse($shift->start); // Parse the start time
            $shiftEnd = Carbon::parse($shift->end);     // Parse the end time
            $breakMinutes = $shift->break_minutes;

            $shiftDuration = ($shiftEnd->diffInMinutes($shiftStart) - $breakMinutes) / 60;
            $plannedWorkingHours += $shiftDuration;
        }

        return $plannedWorkingHours;
    }
}
