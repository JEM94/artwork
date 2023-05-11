<?php

namespace App\Http\Controllers;

use App\Builders\EventBuilder;
use App\Http\Resources\CalendarEventResource;
use App\Models\Area;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Filter;
use App\Models\Project;
use App\Models\Room;
use App\Models\RoomAttribute;
use App\Models\RoomCategory;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Barryvdh\Debugbar\Facades\Debugbar;
use Doctrine\DBAL\Query\QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\ErrorHandler\Debug;

class CalendarController extends Controller
{
    protected ?Carbon $startDate = null;
    protected ?Carbon $endDate = null;

    public function __construct()
    {
    }

    /**
     * Returns all fields that can be filtered by in the calendar
     * @return array
     */
    public function getFilters(): array
    {
        return [
            'projects' => Project::all()->map(fn(Project $project) => [
                'id' => $project->id,
                'label' => $project->name,
                'access_budget' => $project->access_budget
            ]),

            'rooms' => Room::with('adjoining_rooms', 'main_rooms')->get()->map(fn(Room $room) => [
                'id' => $room->id,
                'name' => $room->name,
                'area' => $room->area,
                'room_admins' => $room->room_admins,
                'everyone_can_book' => $room->everyone_can_book,
                'label' => $room->name,
                'adjoining_rooms' => $room->adjoining_rooms->map(fn(Room $adjoining_room) => [
                    'id' => $adjoining_room->id,
                    'label' => $adjoining_room->name
                ]),
                'main_rooms' => $room->main_rooms->map(fn(Room $main_room) => [
                    'id' => $main_room->id,
                    'label' => $main_room->name
                ]),
                'categories' => $room->categories,
                'attributes' => $room->attributes
            ]),

            'roomCategories' => RoomCategory::all()->map(fn(RoomCategory $roomCategory) => [
                'id' => $roomCategory->id,
                'name' => $roomCategory->name,
            ]),

            'roomAttributes' => RoomAttribute::all()->map(fn(RoomAttribute $roomAttribute) => [
                'id' => $roomAttribute->id,
                'name' => $roomAttribute->name,
            ]),

            'eventTypes' => EventType::all()->map(fn(EventType $eventType) => [
                'id' => $eventType->id,
                'name' => $eventType->name,
            ]),

            'areas' => Area::all()->map(fn(Area $area) => [
                'id' => $area->id,
                'name' => $area->name,
            ]),
        ];
    }

    private function get_events_of_day($date_of_day, $room, $projectId = null): array
    {

        $eventsToday = [];
        $today = $date_of_day->format('d.m.Y');

        $room_query = Room::query()->where('id', $room->id)->with('events', function ($query) use ($room) {
            $this->filterEvents($query, null, null, $room, null)->orderBy('start_time', 'ASC');
        })->first();

        foreach ($room_query->events as $event) {
            if (in_array($today, $event->days_of_event)) {
                if (!empty($projectId)) {
                    if ($event->project_id === $projectId) {
                        $eventsToday[] = $event;
                    }
                } else {
                    $eventsToday[] = $event;
                }
            }
        }

        return $eventsToday;
    }

    public function createCalendarData($type = '', ?Project $project = null, ?Room $room = null)
    {

        $calendarType = 'individual';
        $selectedDate = null;
        $this->startDate = Carbon::now()->startOfDay();

        $filterController = new FilterController();

        if ($type === 'dashboard') {
            $this->endDate = Carbon::now()->endOfDay();
        } else {
            $this->endDate = Carbon::now()->addWeeks()->endOfDay();
        }
        if (!empty($project)) {
            $firstEventInProject = $project->events()->orderBy('start_time', 'ASC')->first();
            $lastEventInProject = $project->events()->orderBy('end_time', 'DESC')->first();
            if (!empty($firstEventInProject) && !empty($lastEventInProject)) {
                $this->startDate = Carbon::create($firstEventInProject->start_time)->startOfDay();
                $this->endDate = Carbon::create($lastEventInProject->end_time)->endOfDay();
            } else {
                $this->setDefaultDates();
            }

        } else {
            $this->setDefaultDates();
        }
        $startDay = $this->startDate->format('Y-m-d');
        $endDay = $this->endDate->format('Y-m-d');


        if ($startDay && $endDay) {
            if ($startDay !== $endDay) {
                $calendarType = 'individual';
            } else {
                $calendarType = 'daily';
                $selectedDate = $startDay;
            }
        }

        $calendarPeriod = CarbonPeriod::create($this->startDate, $this->endDate);
        $periodArray = [];

        foreach ($calendarPeriod as $period) {
            $periodArray[] = [
                'day' => $period->format('d.m.'),
                'day_string' => $period->shortDayName,
                'is_weekend' => $period->isWeekend()
            ];
        }

        $eventsWithoutRooms = [];

        if (!empty($room)) {
            $better = collect($calendarPeriod)
                ->mapWithKeys(fn($date) => [
                    $date->format('d.m.') => CalendarEventResource::collection($this->get_events_of_day($date, $room, @$project->id))
                ]);
        } else {
            if (\request('startDate') && \request('endDate')) {
                $startDate = Carbon::create(\request('startDate'))->startOfDay();
                $endDate = Carbon::create(\request('endDate'))->endOfDay();
            } else {
                $startDate = Carbon::now()->startOfDay();
                $endDate = Carbon::now()->addWeeks()->endOfDay();
            }

            $better = $this->filterRooms($startDate, $endDate)
                ->with(['events.room', 'events.project', 'events.creator', 'events' => function ($query) use ($project, $room) {
                    $this->filterEvents($query, null, null, $room, $project)->orderBy('start_time', 'ASC');
                }])
                ->get()
                ->map(fn($room) => collect($calendarPeriod)
                    ->mapWithKeys(fn($date) => [
                        $date->format('d.m.') => CalendarEventResource::collection($this->get_events_of_day($date, $room, @$project->id))
                    ]));

            $events = Event::where('room_id', null)->get();

            $eventsWithoutRooms = CalendarEventResource::collection($events)->resolve();
        }

        return [
            'days' => $periodArray,
            'dateValue' => [$this->startDate->format('Y-m-d'), $this->endDate->format('Y-m-d')],
            // only used for dashboard -> default Dashboard should show Vuecal-Daily calendar with current day
            'calendarType' => $calendarType,
            // Selected Date is needed for change from individual Calendar to VueCal-Daily, so that vuecal knows which date to load
            'selectedDate' => $selectedDate,
            'roomsWithEvents' => $better,
            'eventsWithoutRoom' => $eventsWithoutRooms,
            'filterOptions' => $this->getFilters(),
            'personalFilters' => $filterController->index()
        ];
    }

    public function getEventsOfDay()
    {
        $all_events = Event::query();
        $filteredEvents = $this->filterEvents($all_events, null, null, null, null);
        return $filteredEvents->get();
    }

    public function getEventsAtAGlance($startDate, $endDate): \Illuminate\Support\Collection
    {
        $initialEventQuery = Event::query();

        $filteredEventsQuery = $this->filterEvents($initialEventQuery, $startDate, $endDate, null, null);

        $eventsByRoom = $filteredEventsQuery
            ->with(['room', 'project', 'creator'])
            ->orderBy('start_time', 'ASC')->get();

        return CalendarEventResource::collection($eventsByRoom)->collection->groupBy('room.id');
    }

    private function filterEvents($query, $startDate, $endDate, ?Room $room, ?Project $project)
    {
        $isLoud = request('isLoud');
        $isNotLoud = request('isNotLoud');
        $hasAudience = request('hasAudience');
        $hasNoAudience = request('hasNoAudience');
        $showAdjoiningRooms = request('showAdjoiningRooms');
        $eventTypeIds = request('eventTypeIds');
        $roomIds = request('roomIds');
        $areaIds = request('areaIds');
        $roomAttributeIds = request('roomAttributeIds');
        $roomCategoryIds = request('roomCategoryIds');

        return $query
            ->when($startDate, fn(EventBuilder $builder) => $builder->whereBetween('start_time', [$startDate, $endDate]))
            ->when($endDate, fn(EventBuilder $builder) => $builder->whereBetween('end_time', [$startDate, $endDate]))
            ->when($project, fn(EventBuilder $builder) => $builder->where('project_id', $project->id))
            ->when($room, fn(EventBuilder $builder) => $builder->where('room_id', $room->id))
            ->unless(empty($roomIds) && empty($areaIds) && empty($roomAttributeIds) && empty($roomCategoryIds), fn(EventBuilder $builder) => $builder
                ->whereHas('room', fn(Builder $roomBuilder) => $roomBuilder
                    ->when($roomIds, fn(Builder $roomBuilder) => $roomBuilder->whereIn('rooms.id', $roomIds))
                    ->when($areaIds, fn(Builder $roomBuilder) => $roomBuilder->whereIn('area_id', $areaIds))
                    ->when($showAdjoiningRooms, fn(Builder $roomBuilder) => $roomBuilder->with('adjoining_rooms'))
                    ->when($roomAttributeIds, fn(Builder $roomBuilder) => $roomBuilder
                        ->whereHas('attributes', fn(Builder $roomAttributeBuilder) => $roomAttributeBuilder
                            ->whereIn('room_attributes.id', $roomAttributeIds)))
                    ->when($roomCategoryIds, fn(Builder $roomBuilder) => $roomBuilder
                        ->whereHas('categories', fn(Builder $roomCategoryBuilder) => $roomCategoryBuilder
                            ->whereIn('room_categories.id', $roomCategoryIds)))
                )
            )
            ->unless(empty($eventTypeIds), fn(EventBuilder $builder) => $builder->whereIn('event_type_id', array_map('intval', $eventTypeIds)))
            ->unless(is_null($hasAudience), fn(EventBuilder $builder) => $builder->where('audience', true))
            ->unless(is_null($hasNoAudience), fn(EventBuilder $builder) => $builder->where('audience', null)->orWhere('audience', false))
            ->unless(is_null($isLoud), fn(EventBuilder $builder) => $builder->where('is_loud', true))
            ->unless(is_null($isNotLoud), fn(EventBuilder $builder) => $builder->where('is_loud', false)->orWhere('is_loud', null));
    }

    public function filterRooms($startDate, $endDate)
    {

        # if ($startDate) {
        #     Debugbar::info("Test");
        #     $test = Event::query()->where('room_id', 1)->selectRaw('COUNT(DISTINCT DATE(start_time)) as num_event_days')
        #         ->whereRaw("(DATE(start_time) BETWEEN '$startDate' AND '$endDate' OR DATE(end_time) BETWEEN '$startDate' AND '$endDate')")
        #         ->groupByRaw('DATE(start_time)')
        #         ->havingRaw('COUNT(DISTINCT DATE(start_time)) < DATEDIFF(?, ?) + 1', [$endDate, $startDate])
        #         ->exists();

        #     Debugbar::info($test);
        # }

        return Room::query()
            ->unless(is_null(request('roomIds')),
                fn(Builder $builder) => $builder->whereIn('id', request('roomIds')))
            ->unless(is_null(request('roomAttributeIds')),
                fn(Builder $builder) => $builder->whereHas('attributes', function ($query) {
                    $query->whereIn('room_attributes.id', request('roomAttributeIds'));
                }))
            ->unless(is_null(request('areaIds')),
                fn(Builder $builder) => $builder->whereIn('area_id', request('areaIds')))
            ->unless(is_null(request('roomCategoryIds')),
                fn(Builder $builder) => $builder->whereHas('categories', function ($query) {
                    $query->whereIn('room_categories.id', request('roomCategoryIds'));
                }))
            ->unless(is_null(request('adjoiningNoAudience')) && is_null(request('adjoiningNotLoud')),
                fn(Builder $builder) => $builder
                    ->whereRelation('adjoining_rooms', function ($adjoining_room_query) use ($startDate, $endDate) {
                        $adjoining_room_query->whereRelation('events', function ($event_query) use ($startDate, $endDate) {
                            $event_query
                                ->when($startDate, fn(EventBuilder $builder) => $builder->whereBetween('start_time', [$startDate, $endDate]))
                                ->when($endDate, fn(EventBuilder $builder) => $builder->whereBetween('end_time', [$startDate, $endDate]))
                                ->unless(is_null(request('adjoiningNotLoud')), fn(Builder $builder) => $builder->where('events.is_loud', false))
                                ->unless(is_null(request('adjoiningNoAudience')), fn(Builder $builder) => $builder->where('events.audience', false));
                        });
                    })
                    ->orWhereDoesntHave('adjoining_rooms')
            );
            // ->unless(is_null(request('allDayFree')), fn(Builder $builder) => $builder
            //     ->whereRelation('events', function ($event_query) use ($startDate, $endDate) {
            //         $event_query->selectRaw('COUNT(DISTINCT DATE(start_time)) as num_event_days')
            //             ->whereRaw("(DATE(start_time) BETWEEN '$startDate' AND '$endDate' OR DATE(end_time) BETWEEN '$startDate' AND '$endDate')")
            //             ->groupByRaw('DATE(start_time)')
            //             ->havingRaw('COUNT(DISTINCT DATE(start_time)) < DATEDIFF(?, ?) + 1', [$endDate, $startDate]);
            //     })
            //     ->orWhereDoesntHave('events')
            // );
    }

    private function setDefaultDates()
    {
        if (\request('startDate')) {
            $this->startDate = Carbon::create(\request('startDate'))->startOfDay();
        }
        if (\request('endDate')) {
            $this->endDate = Carbon::create(\request('endDate'))->endOfDay();
        }
    }
}
