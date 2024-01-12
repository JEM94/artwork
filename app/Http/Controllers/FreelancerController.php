<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventTypeResource;
use App\Http\Resources\FreelancerShowResource;
use App\Models\Craft;
use App\Models\EventType;
use App\Models\Freelancer;
use Artwork\Modules\Calendar\Services\CalendarService;
use Artwork\Modules\Project\Models\Project;
use Artwork\Modules\Room\Models\Room;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FreelancerController extends Controller
{
    public function __construct(
        private readonly CalendarService $calendarService
    ) {
    }

    public function store(): \Symfony\Component\HttpFoundation\Response
    {
        $freelancer = Freelancer::create(
            ['profile_image' => 'https://ui-avatars.com/api/?name=NEU&color=7F9CF5&background=EBF4FF']
        );

        return Inertia::location(route('freelancer.show', $freelancer->id));
    }

    public function show(Freelancer $freelancer, CalendarController $shiftPlan): Response
    {
        $showCalendar = $shiftPlan->createCalendarDataForFreelancerShiftPlan($freelancer);
        //$this->getAvailabilityData($freelancer, request('month'))
        $availabilityData = $this->calendarService
            ->getAvailabilityData(freelancer: $freelancer, month: request('month'));

        $selectedDate = Carbon::today();
        $selectedPeriodDate = Carbon::today();
        $vacations = [];
        // get vacations of the selected date (request('showVacationsAndAvailabilities'))
        if (request('showVacationsAndAvailabilities')) {
            $selectedDate = Carbon::parse(request('showVacationsAndAvailabilities'));
            $selectedPeriodDate = Carbon::parse(request('vacationMonth'));
        }

        $vacations = $freelancer->vacations()
            ->where('date', $selectedDate)
            ->orderBy('date', 'ASC')->get();

        $availabilities = $freelancer->availabilities()
            ->where('date', $selectedDate)
            ->orderBy('date', 'ASC')->get();

        $createShowDate = [
            $selectedPeriodDate->locale('de')->isoFormat('MMMM YYYY'),
            $selectedPeriodDate->copy()->startOfMonth()->toDate()
        ];

        return inertia('Freelancer/Show', [
            'freelancer' => new FreelancerShowResource($freelancer),
            //needed for availability calendar
            'calendarData' => $availabilityData['calendarData'],
            'dateToShow' => $availabilityData['dateToShow'],
            'vacations' => $vacations,
            'vacationSelectCalendar' => $this->calendarService
                ->createVacationAndAvailabilityPeriodCalendar(request('vacationMonth')),
            'createShowDate' => $createShowDate,
            'showVacationsAndAvailabilitiesDate' => $selectedDate->format('Y-m-d'),
            //needed for UserShiftPlan
            'dateValue' => $showCalendar['dateValue'],
            'daysWithEvents' => $showCalendar['daysWithEvents'],
            'totalPlannedWorkingHours' => $showCalendar['totalPlannedWorkingHours'],
            'rooms' => Room::all(),
            'eventTypes' => EventTypeResource::collection(EventType::all())->resolve(),
            'projects' => Project::all(),
            'shifts' => $freelancer
                ->shifts()
                ->with(['event', 'event.project', 'event.room'])
                ->orderBy('start', 'ASC')
                ->get(),
        ]);
    }

    public function update(Request $request, Freelancer $freelancer): void
    {
        $freelancer->update($request->only([
                'position',
                'first_name',
                'last_name',
                'email',
                'phone_number',
                'street',
                'zip_code',
                'location',
                'note',
            ]));
    }

    /**
     * @throws AuthorizationException
     */
    public function updateTerms(Freelancer $freelancer, Request $request): void
    {
        $this->authorize('updateTerms', Freelancer::class);

        $freelancer->update($request->only([
            'salary_per_hour',
            'salary_description',
        ]));
    }

    /**
     * @throws AuthorizationException
     */
    public function updateWorkProfile(Freelancer $freelancer, Request $request): RedirectResponse
    {
        $this->authorize('updateWorkProfile', Freelancer::class);

        $freelancer->update([
            'work_name' => $request->get('workName'),
            'work_description' => $request->get('workDescription')
        ]);

        return Redirect::back()->with('success', ['workProfile' => 'Arbeitsprofil erfolgreich aktualisiert']);
    }

    /**
     * @throws AuthorizationException
     */
    public function updateCraftSettings(Freelancer $freelancer, Request $request): RedirectResponse
    {
        $this->authorize('updateWorkProfile', Freelancer::class);

        $freelancer->update([
            'can_work_shifts' => $request->boolean('canBeAssignedToShifts'),
            'can_master' => $request->boolean('canBeUsedAsMasterCraftsman')
        ]);

        return Redirect::back();
    }

    /**
     * @throws AuthorizationException
     */
    public function assignCraft(Freelancer $freelancer, Request $request): RedirectResponse
    {
        $this->authorize('updateWorkProfile', Freelancer::class);

        $craftToAssign = Craft::find($request->get('craftId'));

        if (is_null($craftToAssign)) {
            return Redirect::back();
        }

        if (!$freelancer->assignedCrafts->contains($craftToAssign)) {
            $freelancer->assignedCrafts()->attach(Craft::find($request->get('craftId')));
        }

        return Redirect::back()->with('success', ['craft' => 'Gewerk erfolgreich zugeordnet.']);
    }

    /**
     * @throws AuthorizationException
     */
    public function removeCraft(Freelancer $freelancer, Craft $craft): RedirectResponse
    {
        $this->authorize('updateWorkProfile', Freelancer::class);

        $freelancer->assignedCrafts()->detach($craft);

        return Redirect::back()->with('success', ['craft' => 'Gewerk erfolgreich entfernt.']);
    }

    public function updateProfileImage(Request $request, Freelancer $freelancer): void
    {
        if (!Storage::exists("public/profile-photos")) {
            Storage::makeDirectory("public/profile-photos");
        }

        $file = $request->file('profileImage');
        $original_name = $file->getClientOriginalName();
        $basename = Str::random(20) . $original_name;

        Storage::putFileAs('public/profile-photos', $file, $basename);

        $freelancer->update(['profile_image' => Storage::url('public/profile-photos/' . $basename)]);
    }

    public function destroy(Freelancer $freelancer): RedirectResponse
    {
        $freelancer->delete();

        return Redirect::back();
    }
}
