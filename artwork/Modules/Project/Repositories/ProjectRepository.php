<?php

namespace Artwork\Modules\Project\Repositories;

use Artwork\Core\Database\Repository\BaseRepository;
use Artwork\Modules\Event\Models\Event;
use Artwork\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Scout\Builder;

class ProjectRepository extends BaseRepository
{
    public function findManagers(Project $project): Collection
    {
        return $project->users()->wherePivot('is_manager', '=', 1)->get();
    }

    public function findUsers(Project $project): Collection
    {
        return $project->users()->get();
    }

    /**
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): Project
    {
        return Project::findOrFail($id);
    }

    public function getProjectByCostCenter(string $costCenter): Project|null
    {
        return Project::byCostCenter($costCenter)
            ->with(['table', 'table.columns', 'table.mainPositions.subPositions.subPositionRows.cells'])
            ->without(['shiftRelevantEventTypes', 'state'])
            ->first();
    }

    public function getAll(array $with = []): Collection
    {
        return Project::query()->with($with)->get();
    }

    public function getByName(string $query): Collection
    {
        return Project::byName($query)->get();
    }

    /**
     * @throws ModelNotFoundException
     */
    public function getFirstEvent(int|Project $project): Event|null
    {
        if (!$project instanceof Project) {
            $project = $this->findOrFail($project);
        }

        /** @var Event|null $firstEvent */
        $firstEvent = $project->events()->orderByStartTime()->limit(1)->first();

        return $firstEvent;
    }

    /**
     * @throws ModelNotFoundException
     */
    public function getLastEvent(int|Project $project): Event|null
    {
        if (!$project instanceof Project) {
            $project = $this->findOrFail($project);
        }

        /** @var Event|null $lastEvent */
        $lastEvent = $project->events()->orderByStartTime('DESC')->limit(1)->first();

        return $lastEvent;
    }

    public function getLatestEndingEvent(int|Project $project): Event|null
    {
        if (!$project instanceof Project) {
            $project = $this->findOrFail($project);
        }

        /** @var Event|null $latestEndingEvent */
        $latestEndingEvent = $project->events()->orderBy('end_time', 'DESC')->limit(1)->first();

        return $latestEndingEvent;
    }

    public function getProjects(array $with = []): Collection
    {
        $query = Project::query();

        if (count($with) > 0) {
            $query->with($with);
        }

        return $query->get();
    }

    //@todo für Jason: ignore entfernen
    //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
    public function getProjectQuery(string $search): EloquentBuilder|Builder
    {
            /** @todo für Jason:
             * Scout search wieder einbauen
             */
//        if (strlen($search) > 0) {
//            return Project::search($search);
//        }
        return Project::query();
    }

    public function scoutSearch(string $query): Builder
    {
        return Project::search($query);
    }

    public function pinnedProjects(int $userId): Collection
    {
        return Project::query()
            ->whereNotNull('pinned_by_users')
            ->whereJsonContains('pinned_by_users', [$userId])
            ->without(['shiftRelevantEventTypes'])
            ->with([
                'access_budget' => function ($query): void {
                    $query->without(['calendar_settings', 'calendarAbo', 'shiftCalendarAbo', 'vacations']);
                },
                'categories',
                'genres',
                'sectors',
                'costCenter',
                'groups',
                'managerUsers' => function ($query): void {
                    $query->without(['calendar_settings', 'calendarAbo', 'shiftCalendarAbo', 'vacations']);
                },
                'users' => function ($query): void {
                    $query->without(['calendar_settings', 'calendarAbo', 'shiftCalendarAbo', 'vacations']);
                },
                'writeUsers' => function ($query): void {
                    $query->without(['calendar_settings', 'calendarAbo', 'shiftCalendarAbo', 'vacations']);
                },
                'state',
                'delete_permission_users' => function ($query): void {
                    $query->without(['calendar_settings', 'calendarAbo', 'shiftCalendarAbo', 'vacations']);
                }
            ])
            ->get();
    }

    public function getProjectGroups(): Collection
    {
        return Project::where('is_group', '=', 1)->with('groups')->get();
    }

    public function getMyLastProject(int $userId): ?Project
    {
        return Project::where('user_id', $userId)->orderBy('updated_at', 'DESC')->first();
    }
}
