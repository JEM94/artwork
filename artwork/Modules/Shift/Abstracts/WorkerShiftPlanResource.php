<?php

namespace Artwork\Modules\Shift\Abstracts;

use Artwork\Modules\Freelancer\Models\Freelancer;
use Artwork\Modules\ServiceProvider\Models\ServiceProvider;
use Artwork\Modules\Shift\Models\Shift;
use Artwork\Modules\User\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin User|Freelancer|ServiceProvider
 */
class WorkerShiftPlanResource extends JsonResource
{
    private Carbon $startDate;

    private Carbon $endDate;

    /**
     * @return array<string, mixed>
     */
    //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'shifts' => $this->loadDesiredShiftsForShiftPlan(),
            'assigned_craft_ids' => $this->getAssignedCraftIdsAttribute(),
            'shift_ids' => $this->getShiftIdsBetweenStartDateAndEndDate($this->startDate, $this->endDate),
            'type' => $this->getTypeAttribute(),
            'shift_qualifications' => $this->getAttribute('shiftQualifications'),
            'managing_craft_ids' => $this->getManagingCraftIds(),
        ];
    }

    final public function setStartDate(Carbon $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    final public function setEndDate(Carbon $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    protected function loadDesiredShiftsForShiftPlan(): Collection
    {
        return $this->getAttribute('shifts')->map(
            function (Shift $shift): array {
                $event = $shift->getAttribute('event');

                return [
                    'id' => $shift->getAttribute('id'),
                    'pivotId' => $shift->getRelation('pivot')->getAttribute('id'),
                    'craftAbbreviationUser' => $shift->getRelation('pivot')->getAttribute('craft_abbreviation'),
                    'start' => $shift->getAttribute('start'),
                    'end' => $shift->getAttribute('end'),
                    'description' => $shift->getAttribute('description'),
                    'craftAbbreviation' => $shift->getAttribute('craft')->getAttribute('abbreviation'),
                    'days_of_shift' => $shift->getAttribute('days_of_shift'),
                    'start_of_shift' => $shift->getAttribute('start_date')->format('d.m.Y'),
                    'roomName' => $event->getAttribute('room')?->getAttribute('name'),
                    'eventName' => $event->getAttribute('name') ?? $event->getAttribute('eventName'),
                    'eventTypeAbbreviation' => $event->getAttribute('event_type')->getAttribute('abbreviation'),
                ];
            }
        );
    }
}
