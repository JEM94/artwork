<?php

namespace App\Http\Resources\ProjectResources;

use App\Http\Resources\DepartmentIndexResource;
use App\Http\Resources\UserResourceWithoutShifts;
use App\Models\ProjectStates;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ProjectCommentResource extends JsonResource
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
            'isMemberOfADepartment' => $this->departments
                ->contains(fn ($department) => $department->users->contains(Auth::user())),
            'key_visual_path' => $this->key_visual_path,

            'write_auth' => $this->writeUsers,
            'users' => UserResourceWithoutShifts::collection($this->users)->resolve(),

            //needed for ProjectShowHeaderComponent
            'project_history' => $historyArray,
            'delete_permission_users' => $this->delete_permission_users,

            //needed for project Second Sidenav
            'entry_fee' => $this->entry_fee,
            'registration_required' => $this->registration_required,
            'register_by' => $this->register_by,
            'registration_deadline' => $this->registration_deadline,
            'closed_society' => $this->closed_society,
            'num_of_guests' => $this->num_of_guests,
            'project_managers' => $this->managerUsers,
            'departments' => DepartmentIndexResource::collection($this->departments)->resolve(),
            'state' => ProjectStates::find($this->state),
            'comments' => $this->comments->map(fn ($comment) => [
                'id' => $comment->id,
                'text' => $comment->text,
                'created_at' => $comment->created_at->format('d.m.Y, H:i'),
                'user' => $comment->user
            ]),
        ];
    }
}
