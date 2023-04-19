<?php

namespace App\Http\Controllers;

use App\Builders\EventBuilder;
use App\Enums\NotificationConstEnum;
use App\Events\OccupancyUpdated;
use App\Http\Requests\EventAcceptionRequest;
use App\Http\Requests\EventIndexRequest;
use App\Http\Requests\EventStoreRequest;
use App\Http\Requests\EventUpdateRequest;
use App\Http\Resources\CalendarEventCollectionResource;
use App\Http\Resources\CalendarEventResource;
use App\Http\Resources\EventShowResource;
use App\Http\Resources\EventTypeResource;
use App\Http\Resources\ProjectIndexAdminResource;
use App\Http\Resources\TaskIndexResource;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Project;
use App\Models\Room;
use App\Models\Scheduling;
use App\Models\SeriesEvents;
use App\Models\Task;
use App\Models\User;
use App\Support\Services\CollisionService;
use App\Support\Services\HistoryService;
use Barryvdh\Debugbar\Facades\Debugbar;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Response;

class EventController extends Controller
{

    protected ?NotificationController $notificationController = null;
    protected ?\stdClass $notificationData = null;
    protected ?CollisionService $collisionService = null;
    protected ?HistoryController $history = null;

    public function __construct()
    {
        $this->collisionService = new CollisionService();
        $this->notificationController = new NotificationController();
        $this->notificationData = new \stdClass();
        $this->notificationData->event = new \stdClass();
        $this->notificationData->type = NotificationConstEnum::NOTIFICATION_EVENT_CHANGED;
        $this->history = new HistoryController('App\Models\Event');
    }

    public function viewEventIndex(Request $request): Response
    {
        return inertia('Events/EventManagement', [
            'eventTypes' => EventTypeResource::collection(EventType::all())->resolve()
        ]);
    }

    public function showDashboardPage(Request $request): Response
    {
        $calendar = new CalendarController();
        $showCalendar = $calendar->createCalendarData();

        $projects = Project::query()->with( ['managerUsers'])->get();

        $tasks = Task::query()
            ->whereHas('checklist', fn (Builder $checklistBuilder) => $checklistBuilder
                ->where('user_id', Auth::id())
            )
            ->orWhereHas('task_users', fn (Builder $userBuilder) => $userBuilder
                ->where('user_id', Auth::id())
            )
            ->get();

        return inertia('Dashboard', [
            'projects' => ProjectIndexAdminResource::collection($projects)->resolve(),
            'tasks' => TaskIndexResource::collection($tasks)->resolve(),
            'eventTypes' => EventTypeResource::collection(EventType::all())->resolve(),
            'calendar' => $showCalendar['roomsWithEvents'],
            'days' => $showCalendar['days'],
            'dateValue'=>$showCalendar['dateValue'],
            'calendarType' => $showCalendar['calendarType'],
            'rooms' => Room::all(),
        ]);
    }

    public function viewRequestIndex(Request $request): Response
    {
        // Todo: filter room for visible for authenticated user
        // should be like: Event::where($event->room->room_admins->contains(Auth::id()))->map(fn($event) => [
        $events = Event::query()
            ->where('occupancy_option', true)
            ->get();

        return inertia('Events/EventRequestsManagement', [
            'event_requests' => EventShowResource::collection($events)->resolve(),
        ]);
    }

    public function storeEvent(EventStoreRequest $request): CalendarEventResource
    {
        // Adjoining Room / Event check
        $this->adjoiningRoomsCheck($request);
        $firstEvent = Event::create($request->data());
        if ($request->get('projectName')) {
            $this->associateProject($request, $firstEvent);
        }

        if($request->is_series){
            $series = SeriesEvents::create([
                'frequency_id' => $request->seriesFrequency,
                'end_date' => $request->seriesEndDate
            ]);
            $firstEvent->update(['is_series' => true, 'series_id' => $series->id]);
            $endSeriesDate = Carbon::parse($request->seriesEndDate);
            $startDate = Carbon::parse($request->start)->setTimezone(config('app.timezone'));
            $endDate = Carbon::parse($request->end)->setTimezone(config('app.timezone'));
            $whileEndDate = Carbon::parse($request->end)->setTimezone(config('app.timezone'));
            if($request->seriesFrequency === 1){
                while ($whileEndDate->addDay() <= $endSeriesDate) {
                    $startDate = $startDate->addDay();
                    $endDate = $endDate->addDay();
                    $this->createSeriesEvent($startDate, $endDate, $request, $series);
                }
            }
            if($request->seriesFrequency === 2){
                while ($whileEndDate->addWeek() <= $endSeriesDate) {
                    $startDate = $startDate->addWeek();
                    $endDate = $endDate->addWeek();
                    $this->createSeriesEvent($startDate, $endDate, $request, $series);
                }
            }
            if($request->seriesFrequency === 3){
                while ($whileEndDate->addWeeks(2) <= $endSeriesDate) {
                    $startDate = $startDate->addWeeks(2);
                    $endDate = $endDate->addWeeks(2);
                    $this->createSeriesEvent($startDate, $endDate, $request, $series);
                }
            }
            if($request->seriesFrequency === 4){
                while ($whileEndDate->addMonth() <= $endSeriesDate) {
                    $startDate = $startDate->addMonth();
                    $endDate = $endDate->addMonth();
                    $this->createSeriesEvent($startDate, $endDate, $request, $series);
                }
            }
        }

        if(!empty($firstEvent->project()->get())){
            $eventProject = $firstEvent->project()->first();
            if($eventProject){
                $projectHistory = new HistoryController('App\Models\Project');
                $projectHistory->createHistory($eventProject->id, 'Ablaufplan hinzugefügt');
            }
        }

        if($request->isOption){
            $this->createRequestNotification($request, $firstEvent);
        }

        broadcast(new OccupancyUpdated())->toOthers();

        return new CalendarEventResource($firstEvent);
    }

    private function createSeriesEvent($startDate,$endDate, $request, $series){
        $event = Event::create([
            'name' => $request->title,
            'eventName' => $request->eventName,
            'description' => $request->description,
            'start_time' => $startDate,
            'end_time' => $endDate,
            'occupancy_option' => $request->isOption,
            'audience' => $request->audience,
            'is_loud' => $request->isLoud,
            'event_type_id' => $request->eventTypeId,
            'room_id' => $request->roomId,
            'user_id' => Auth::id(),
            'project_id' => $request->projectId,
            'is_series' => true,
            'series_id' => $series->id,
        ]);
        if ($request->get('projectName')) {
            $this->associateProject($request, $event);
        }
    }

    private function adjoiningRoomsCheck(EventStoreRequest $request) {
        $joiningEvents = $this->collisionService->adjoiningCollision($request);
        foreach ($joiningEvents as $joiningEvent){
            foreach ($joiningEvent as $conflict){
                $user = User::find($conflict->user_id);
                if($user->id === Auth::id()){
                    continue;
                }
                if($request->audience){
                    $this->createAdjoiningAudienceNotification($conflict, $user);
                }
                if($request->isLoud){
                    $this->createAdjoiningLoudNotification($conflict, $user);
                }
            }
        }
        $this->authorize('create', Event::class);

        if($this->collisionService->getCollision($request)->count() > 0){
            $collisions = $this->collisionService->getConflictEvents($request);
            if(!empty($collisions)){
                foreach ($collisions as $collision){
                    $this->createConflictNotification($collision);
                }
            }
        }
    }

    private function createAdjoiningAudienceNotification($conflict, User $user) {
        $this->notificationData->type = NotificationConstEnum::NOTIFICATION_LOUD_ADJOINING_EVENT;
        $this->notificationData->title = 'Termin mit Publikum im Nebenraum';
        $this->notificationData->conflict = $conflict;
        $this->notificationData->created_by = Auth::user();
        $broadcastMessage = [
            'id' => rand(1, 1000000),
            'type' => 'error',
            'message' => $this->notificationData->title
        ];
        $this->notificationController->create($user, $this->notificationData, $broadcastMessage);
    }

    private function createAdjoiningLoudNotification($conflict, User $user) {
        $this->notificationData->type = NotificationConstEnum::NOTIFICATION_LOUD_ADJOINING_EVENT;
        $this->notificationData->title = 'Lauter Termin im Nebenraum';
        $this->notificationData->conflict = $conflict;
        $this->notificationData->created_by = Auth::user();
        $broadcastMessage = [
            'id' => rand(1, 1000000),
            'type' => 'error',
            'message' => $this->notificationData->title
        ];
        $this->notificationController->create($user, $this->notificationData, $broadcastMessage);
    }

    private function createConflictNotification($collision) {
        // TODO:: FIX NOTIFICATION -> [2023-02-08 09:43:28] staging.ERROR: Call to a member function notificationSettings() on null {"userId":1,"exception":"[object] (Error(code: 0): Call to a member function notificationSettings() on null at /home/ploi/artwork.caldero-systems.de/app/Notifications/ConflictNotification.php:37)
        //[stacktrace]
        $this->notificationData->type = NotificationConstEnum::NOTIFICATION_CONFLICT;
        $this->notificationData->title = 'Terminkonflikt';
        $this->notificationData->conflict = $collision;
        $this->notificationData->created_by = Auth::user();
        $broadcastMessage = [
            'id' => rand(1, 1000000),
            'type' => 'error',
            'message' => $this->notificationData->title
        ];
        if(!empty($collision['created_by'])){
            $this->notificationController->create($collision['created_by'], $this->notificationData, $broadcastMessage);
        }
    }

    private function associateProject($request, $event) {
        $project = Project::create(['name' => $request->get('projectName')]);
        $project->users()->save(Auth::user(), ['access_budget' => true]);
        $projectController = new ProjectController();
        $projectController->generateBasicBudgetValues($project);
        $event->project()->associate($project);
        $event->save();
    }

    private function createRequestNotification($request, $event) {
        $this->notificationData->type = NotificationConstEnum::NOTIFICATION_ROOM_REQUEST;
        $this->notificationData->title = 'Neue Raumanfrage';
        $this->notificationData->event = $event;
        $this->notificationData->accepted = false;
        $broadcastMessage = [
            'id' => rand(1, 1000000),
            'type' => 'success',
            'message' => $this->notificationData->title
        ];
        $this->notificationData->created_by = Auth::user();
        $room = Room::find($request->roomId);
        $admins = $room->room_admins()->get();
        if(!empty($admins)){
            foreach ($admins as $admin){
                $this->notificationController->create($admin, $this->notificationData, $broadcastMessage);
            }
        } else {
            $user = User::find($room->user_id);
            $this->notificationController->create($user, $this->notificationData, $broadcastMessage);
        }
    }

    /**
     * @param EventUpdateRequest $request
     * @param Event $event
     * @param HistoryService $historyService
     * @return CalendarEventResource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function updateEvent(EventUpdateRequest $request, Event $event): CalendarEventResource
    {


        DatabaseNotification::query()
            ->whereJsonContains("data->type", "NOTIFICATION_UPSERT_ROOM_REQUEST")
            ->orWhereJsonContains("data->type", "ROOM_REQUEST")
            ->whereJsonContains("data->event->id", $event->id)
            ->delete();

        if($request->roomChange){
            $this->notificationData->title = 'Raumanfrage mit Raumänderung bestätigt';
            $this->notificationData->type = NotificationConstEnum::NOTIFICATION_ROOM_REQUEST;
            $this->notificationData->event = $event;
            $this->notificationData->accepted = true;
            $this->notificationData->created_by = Auth::user();
            $broadcastMessage = [
                'id' => rand(1, 1000000),
                'type' => 'success',
                'message' => $this->notificationData->title
            ];
            $this->notificationController->create($event->creator, $this->notificationData, $broadcastMessage);
        }

        $this->authorize('update', $event);

        $oldEventDescription = $event->description;
        $oldEventRoom = $event->room_id;
        $oldEventProject = $event->project_id;
        $oldEventName = $event->eventName;
        $oldEventType = $event->event_type_id;
        $oldEventStartDate = $event->start_time;
        $oldEventEndDate = $event->end_time;
        $oldIsLoud = $event->is_loud;
        $oldAudience = $event->audience;


        $event->update($request->data());

        if ($request->get('projectName')) {
            $project = Project::create(['name' => $request->get('projectName')]);
            $project->users()->save(Auth::user(), ['access_budget' => true]);
            $projectController = new ProjectController();
            $projectController->generateBasicBudgetValues($project);
            $event->project()->associate($project);
            $event->save();
        }

        if(!empty($event->project_id)){
            $eventProject = $event->project()->first();
            $projectHistory = new HistoryController('App\Models\Project');
            $projectHistory->createHistory($eventProject->id, 'Ablaufplan geändert');
        }

        $newEventDescription = $event->description;
        $newEventRoom = $event->room_id;
        $newEventProject = $event->project_id;
        $newEventName = $event->eventName;
        $newEventType = $event->event_type_id;
        $newEventStartDate = $event->start_time;
        $newEventEndDate = $event->end_time;
        $newIsLoud = $event->is_loud;
        $newAudience = $event->audience;


        $this->checkShortDescriptionChanges($event->id, $oldEventDescription, $newEventDescription);
        $this->checkRoomChanges($event->id, $oldEventRoom, $newEventRoom);
        $this->checkProjectChanges($event->id, $oldEventProject, $newEventProject);
        $this->checkEventNameChanges($event->id, $oldEventName, $newEventName);
        $this->checkEventTypeChanges($event->id, $oldEventType, $newEventType);
        $this->checkDateChanges($event->id, $oldEventStartDate, $newEventStartDate, $oldEventEndDate, $newEventEndDate);
        $this->checkEventOptionChanges($event->id, $oldIsLoud, $newIsLoud, $oldAudience, $newAudience);

        $this->createEventScheduleNotification($event);

        // get time diff
        $oldEventStartDateDays = Carbon::create($oldEventStartDate);
        $oldEventEndDate = Carbon::create($oldEventEndDate);

        $newEventStartDateDays = Carbon::parse($newEventStartDate);
        $newEventEndDate = Carbon::parse($newEventEndDate);

        $diffStartDays = $oldEventStartDateDays->diffInDays($newEventStartDateDays, false);
        $diffEndDays = $oldEventEndDate->diffInDays($newEventEndDate, false);

        $diffStartMinutes = $oldEventStartDateDays->diffInRealMinutes($newEventStartDate, false);
        $diffEndMinutes = $oldEventStartDateDays->diffInMinutes($newEventStartDate, false);


        if($request->allSeriesEvents){
            if($event->is_series){
                $seriesEvents = Event::where('series_id', $event->series_id)->get();
                foreach ($seriesEvents as $seriesEvent){
                    $seriesEvent->update([
                        'name' => $event->name,
                        'eventName' => $event->eventName,
                        'description' => $event->description,
                        'occupancy_option' => $event->occupancy_option,
                        'audience' => $event->audience,
                        'is_loud' => $event->is_loud,
                        'event_type_id' => $event->event_type_id,
                        'room_id' => $event->room_id,
                        'project_id' => $event->project_id,
                        'start_time' => Carbon::parse($seriesEvent->start_time)->addDays($diffStartDays)->addMinutes($diffStartMinutes),
                        'end_time' => Carbon::parse($seriesEvent->end_time)->addDays($diffEndDays)->addMinutes($diffEndMinutes),
                    ]);
                }
            }
        }

        return new CalendarEventResource($event);
    }

    /**
     * @param Event $event
     * @return void
     */
    private function createEventScheduleNotification(Event $event): void
    {
        $schedule = new SchedulingController();
        if(!empty($event->project)){
            foreach ($event->project->users->all() as $eventUser){
                $schedule->create($eventUser->id, 'EVENT_CHANGES', 'EVENT', $event->id);
            }
        } else {
            $schedule->create($event->creator->id, 'EVENT_CHANGES', 'EVENT', $event->id);
        }
    }

    public function acceptEvent(EventAcceptionRequest $request, Event $event): \Illuminate\Http\RedirectResponse
    {
        DatabaseNotification::query()
            ->whereJsonContains("data->type", "NOTIFICATION_UPSERT_ROOM_REQUEST")
            ->orWhereJsonContains("data->type", "ROOM_REQUEST")
            ->whereJsonContains("data->event->id", $event->id)
            ->delete();

        $event->occupancy_option = false;
        if (!$request->get('accepted')) {
            $this->notificationData->title = 'Raumanfrage abgelehnt';
            $this->history->createHistory($event->id, 'Raum abgelehnt');
            $this->notificationData->accepted = false;
            $event->declined_room_id = $event->room_id;
            $event->room_id = null;
            $broadcastMessage = [
                'id' => rand(1, 1000000),
                'type' => 'error',
                'message' => $this->notificationData->title
            ];

        } else {
            $this->notificationData->title = 'Raumanfrage bestätigt';
            $this->history->createHistory($event->id, 'Raum bestätigt');
            $this->notificationData->accepted = true;
            $broadcastMessage = [
                'id' => rand(1, 1000000),
                'type' => 'success',
                'message' => $this->notificationData->title
            ];
        }
        $event->save();
        $this->notificationData->type = NotificationConstEnum::NOTIFICATION_UPSERT_ROOM_REQUEST;
        $this->notificationData->event = $event;
        $this->notificationData->created_by = User::where('id', Auth::id())->first();
        $this->notificationController->create($event->creator, $this->notificationData, $broadcastMessage);

        return Redirect::back();
    }

    public function getCollisionCount(Request $request): int
    {
        $start = Carbon::parse($request->query('start'))->setTimezone(config('app.timezone'));
        $end = Carbon::parse($request->query('end'))->setTimezone(config('app.timezone'));

        return Event::query()
            ->whereOccursBetween($start, $end, true)
            ->where('room_id', $request->query('roomId'))
            ->where('id', '!=', $request->query('eventId'))
            ->count();
    }

    public function eventIndex(EventIndexRequest $request): array
    {
        $calendarFilters = json_decode($request->input('calendarFilters'));
        $projectId = $request->get('projectId');
        $roomId = $request->get('roomId');
        $roomIds = $calendarFilters->roomIds;
        $areaIds = $calendarFilters->areaIds;
        $eventTypeIds = $calendarFilters->eventTypeIds;
        $roomAttributeIds = $calendarFilters->roomAttributeIds;
        $roomCategoryIds = $calendarFilters->roomCategoryIds;
        $isLoud = $calendarFilters->isLoud;
        $isNotLoud = $calendarFilters->isNotLoud;
        $hasAudience = $calendarFilters->hasAudience;
        $hasNoAudience = $calendarFilters->hasNoAudience;
        $showAdjoiningRooms = $calendarFilters->showAdjoiningRooms;

        if($request->get('projectId')){
            $eventsWithoutRoom = Event::query()->whereNull('room_id')->where('project_id', $request->get('projectId'))->get();
        }else{
            $eventsWithoutRoom = Event::query()->whereNull('room_id')->where('user_id',Auth::id())->get();
        }

        Debugbar::info($roomAttributeIds);
        Debugbar::info($roomCategoryIds);

        $events = Event::query()
            // eager loading
            ->withCollisionCount()
            ->with('room')
            // filter for different pages
            ->whereOccursBetween(Carbon::parse($request->get('start')), Carbon::parse($request->get('end')))
            ->when($projectId, fn (EventBuilder $builder) => $builder->where('project_id', $projectId))
            ->when($roomId, fn (EventBuilder $builder) => $builder->where('room_id', $roomId))
            //war in alter Version, relevant für dich Paul ?
            ->applyFilter(json_decode($request->input('calendarFilters'), true))
            // user applied filters
            ->unless(empty($roomIds) && empty($areaIds) && empty($roomAttributeIds) && empty($roomCategoryIds), fn (EventBuilder $builder) => $builder
                ->whereHas('room', fn (Builder $roomBuilder) => $roomBuilder
                    ->when($roomIds, fn (Builder $roomBuilder) => $roomBuilder->whereIn('rooms.id', $roomIds))
                    ->when($areaIds, fn (Builder $roomBuilder) => $roomBuilder->whereIn('area_id', $areaIds))
                    ->when($showAdjoiningRooms, fn(Builder $roomBuilder) => $roomBuilder->with('adjoining_rooms'))
                    ->when($roomAttributeIds, fn (Builder $roomBuilder) => $roomBuilder
                        ->whereHas('attributes', fn (Builder $roomAttributeBuilder) => $roomAttributeBuilder
                            ->whereIn('room_attributes.id', $roomAttributeIds)))
                    ->when($roomCategoryIds, fn (Builder $roomBuilder) => $roomBuilder
                        ->whereHas('categories', fn (Builder $roomCategoryBuilder) => $roomCategoryBuilder
                            ->whereIn('room_categories.id', $roomCategoryIds)))
                )
            )
            ->unless(empty($eventTypeIds), fn (EventBuilder $builder) => $builder->whereIn('event_type_id', $eventTypeIds))
            ->unless(is_null($isLoud), fn (EventBuilder $builder) => $builder->where('is_loud', $isLoud))
            ->unless(is_null($isNotLoud), fn (EventBuilder $builder) => $builder->where('is_loud',null))
            ->unless(is_null($hasAudience), fn (EventBuilder $builder) => $builder->where('audience', $hasAudience))
            ->unless(is_null($hasNoAudience), fn (EventBuilder $builder) => $builder->where('audience', null))
            ->get();

        return [
            'events' => new CalendarEventCollectionResource($events),
            'eventsWithoutRoom' => new CalendarEventCollectionResource($eventsWithoutRoom),
        ];
    }

    public function getTrashed(): Response|\Inertia\ResponseFactory
    {
        return inertia('Trash/Events', [
            'trashed_events' => Event::onlyTrashed()->get()->map(fn ($event) => [
                'id' => $event->id,
                'name' => $event->eventName,
                'project'=> $event->project,
                'event_type' => $event->event_type,
                'start' => $event->start_time->format('d.m.Y, H:i'),
                'end' => $event->end_time->format('d.m.Y, H:i'),
                'room_name' => $event->room?->label,
            ])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Event $event
     * @return JsonResponse
     */
    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);

        if(!empty($event->project_id)){
            $eventProject = $event->project()->first();
            $projectHistory = new HistoryController('App\Models\Project');
            $projectHistory->createHistory($eventProject->id, 'Ablaufplan gelöscht');
        }

        $event->subEvents()->delete();

        broadcast(new OccupancyUpdated())->toOthers();
        $event->delete();

        // create and send notification to event owner
        $this->notificationData->title = 'Termin abgesagt';
        $this->notificationData->event = $event;
        $this->notificationData->created_by = User::where('id', Auth::id())->first();
        $broadcastMessage = [
            'id' => rand(1, 1000000),
            'type' => 'error',
            'message' => $this->notificationData->title
        ];
        $this->notificationController->create($event->creator()->get(), $this->notificationData, $broadcastMessage);
    }

    public function forceDelete(int $id): \Illuminate\Http\RedirectResponse
    {
        $event = Event::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $event);
        $event->forceDelete();
        broadcast(new OccupancyUpdated())->toOthers();

        return Redirect::route('events.trashed')->with('success', 'Event deleted');
    }

    public function restore(int $id): \Illuminate\Http\RedirectResponse
    {
        $event = Event::onlyTrashed()->findOrFail($id);
        $event->restore();
        broadcast(new OccupancyUpdated())->toOthers();

        return Redirect::route('events.trashed')->with('success', 'Event restored');
    }

    /**
     * @param $eventId
     * @param $oldEventStartDate
     * @param $newEventStartDate
     * @param $oldEventEndDate
     * @param $newEventEndDate
     * @return void
     */
    private function checkDateChanges($eventId, $oldEventStartDate, $newEventStartDate, $oldEventEndDate, $newEventEndDate): void
    {
        if(strtotime($oldEventStartDate) !== strtotime($newEventStartDate) || strtotime($oldEventEndDate) !== strtotime($newEventEndDate)){
            $this->history->createHistory($eventId, 'Datum/Uhrzeit geändert');
        }
    }

    /**
     * @param $eventId
     * @param $oldType
     * @param $newType
     * @return void
     */
    private function checkEventTypeChanges($eventId, $oldType, $newType): void
    {
        if($oldType !== $newType){
            $this->history->createHistory($eventId, 'Termintyp geändert');
        }
    }

    /**
     * @param $eventId
     * @param $oldName
     * @param $newName
     * @return void
     */
    private function checkEventNameChanges($eventId, $oldName, $newName): void
    {
        if($oldName === null && $newName !== null){
            $this->history->createHistory($eventId, 'Terminname hinzugefügt');
        }

        if($oldName !== $newName && $newName !== null && $oldName !== null){
            $this->history->createHistory($eventId, 'Terminname geändert');
        }

        if($oldName !== null && $newName === null){
            $this->history->createHistory($eventId, 'Terminname gelöscht');
        }
    }

    /**
     * @param $eventId
     * @param $oldProject
     * @param $newProject
     * @return void
     */
    private function checkProjectChanges($eventId, $oldProject, $newProject): void
    {
        if($newProject !== null && $oldProject === null){
            $this->history->createHistory($eventId, 'Projektzuordnung hinzugefügt');
        }

        if($oldProject !== null && $newProject === null ){
            $this->history->createHistory($eventId, 'Projektzuordnung gelöscht');
        }

        if($newProject !== null && $oldProject !== null && $newProject !== $oldProject ){
            $this->history->createHistory($eventId, 'Projektzuordnung geändert');
        }
    }

    /**
     * @param $eventId
     * @param $oldRoom
     * @param $newRoom
     * @return void
     */
    private function checkRoomChanges($eventId, $oldRoom, $newRoom): void
    {
        if($oldRoom !== $newRoom){
            $this->history->createHistory($eventId, 'Raum geändert');
        }
    }

    /**
     * @param int $eventId
     * @param $oldDescription
     * @param $newDescription
     * @return void
     */
    private function checkShortDescriptionChanges(int $eventId, $oldDescription, $newDescription): void
    {
        if($newDescription === null && $oldDescription !== null){
            $this->history->createHistory($eventId, 'Terminnotiz gelöscht');
        }
        if($oldDescription === null && $newDescription !== null){
            $this->history->createHistory($eventId, 'Terminnotiz hinzugefügt');
        }
        if($oldDescription !== $newDescription && $oldDescription !== null && $newDescription !== null){
            $this->history->createHistory($eventId, 'Terminnotiz geändert');
        }
    }

    private function checkEventOptionChanges(int $eventId, $isLoudOld, $isLoudNew, $audienceOld, $audienceNew){
        if($isLoudOld !== $isLoudNew || $audienceOld !== $audienceNew){
            $this->history->createHistory($eventId, 'Termineigenschaft geändert');
        }
    }

}
