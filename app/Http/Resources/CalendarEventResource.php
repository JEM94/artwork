<?php

namespace App\Http\Resources;

use App\Models\UserCalendarSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class CalendarEventResource extends JsonResource
{
    /**
     * @var null
     */
    public static $wrap = null;

    /**
     * @var UserCalendarSettings
     */
    private UserCalendarSettings $userCalendarSettings;

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $this->userCalendarSettings = Auth::user()->calendar_settings;

        if ($this->occupancy_option) {
            $classString = $this->event_type->svg_name . ' ' . 'occupancy_option_' . $this->event_type->svg_name;
        } else {
            $classString = $this->event_type->svg_name;
        }

        $output = [
            'resource' => class_basename($this),
            'id' => $this->id,
            'start' => $this->start_time->utc()->toIso8601String(),
            'startTime' => $this->start_time,
            'end' => $this->end_time->utc()->toIso8601String(),
            'title' => $this->project?->name ?: $this->eventName ? : $this->event_type->name,
            'alwaysEventName' => $this->eventName,
            'eventName' => $this->eventName,
            'event_type' => $this->event_type,
            'description' => $this->description,
            'audience' => $this->audience,
            'isLoud' => $this->is_loud,
            'projectId' => $this->project_id,
            'projectName' => $this->project?->name,
            'roomId' => $this->room_id,
            'roomName' => $this->room?->name,
            'declinedRoomId' => $this->declined_room_id,
            'eventTypeId' => $this->event_type_id,
            'eventTypeName' => $this->event_type->name,
            'eventTypeAbbreviation' => $this->event_type->abbreviation,
            'class' => $classString,
            'areaId' => $this->room?->area_id,
            'created_at' => $this->created_at?->format('d.m.Y, H:i'),
            'created_by' => $this->creator,
            'occupancy_option' => $this->occupancy_option,
            'projectLeaders' => $this->project?->managerUsers,
            //'project' => new ProjectInEventResource($this->project),
            'project' => new ProjectInCalendarResource($this->project),
            'collisionCount' => $this->collision_count,
            'is_series' => $this->is_series,
            'series_id' => $this->series_id,
            'option_string' => $this->option_string,
            'series' => $this->series,
            'allDay' => $this->allDay,
            // to display rooms as split
            'split' => $this->room_id,
            // Todo Add Authorization
            'resizable' => true,
            'draggable' => true,
            'canEdit' => Auth::user()->can('update', $this->resource),
            'canAccept' => Auth::user()->can('update', $this->resource),
            'canDelete' => Auth::user()->can('delete', $this->resource),
            'subEvents' => SubEventResource::collection($this->subEvents),
            'comments' => $this->comments,
            'shifts' => $this->shifts,
        ];

        if (!$this->userCalendarSettings->work_shifts) {
            if (isset($output['shifts'])) {
                if (array_key_exists('shifts', $output)) {
                    unset($output['shifts']);
                }
            }
        }

        return $output;
    }
}
