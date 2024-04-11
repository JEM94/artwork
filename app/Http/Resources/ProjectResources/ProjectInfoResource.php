<?php

namespace App\Http\Resources\ProjectResources;

use App\Http\Resources\DepartmentIndexResource;
use App\Http\Resources\ProjectFileResource;
use App\Http\Resources\ProjectHeadlineResource;
use App\Http\Resources\UserResourceWithoutShifts;
use Artwork\Modules\Project\Models\ProjectStates;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin \Artwork\Modules\Project\Models\Project
 */
class ProjectInfoResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
    public function toArray($request): array
    {
        $historyArray = [];
        $historyComplete = $this->historyChanges()->all();

        foreach ($historyComplete as $history) {
            $historyArray[] = [
                'changes' => json_decode($history->changes),
                'created_at' => $history->created_at->diffInHours() < 24
                    ? $history->created_at->diffForHumans()
                    : $history->created_at->format('d.m.Y, H:i'),
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'description_without_html' => strip_tags($this->description),
            'project_headlines' => ProjectHeadlineResource::collection($this->headlines->sortBy('order'))->resolve(),
            'isMemberOfADepartment' => $this->departments
                ->contains(fn ($department) => $department->users->contains(Auth::user())),
            'key_visual_path' => $this->key_visual_path,
            'project_files' => ProjectFileResource::collection($this->project_files),
            'write_auth' => $this->writeUsers,
            'users' => UserResourceWithoutShifts::collection($this->users)->resolve(),
            //needed for ProjectShowHeaderComponent
            'project_history' => $historyArray,
            'delete_permission_users' => $this->delete_permission_users,
            'state' => ProjectStates::find($this->state),
            //needed for project Second Sidenav
            'entry_fee' => $this->entry_fee,
            'registration_required' => $this->registration_required,
            'register_by' => $this->register_by,
            'registration_deadline' => $this->registration_deadline,
            'closed_society' => $this->closed_society,
            'num_of_guests' => $this->num_of_guests,
            'project_managers' => $this->managerUsers,
            'departments' => DepartmentIndexResource::collection($this->departments)->resolve(),
            'is_group' => $this->is_group
        ];
    }
}