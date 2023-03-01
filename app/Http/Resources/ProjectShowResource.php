<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin \App\Models\Project
 */
class ProjectShowResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $historyArray = [];
        $historyComplete = $this->historyChanges()->all();

        foreach ($historyComplete as $history){
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
            'number_of_participants' => $this->number_of_participants,
            'is_group' => $this->is_group,
            'group' => $this->groups,
            'sectors' => $this->sectors,
            'categories' => $this->categories,
            'genres' => $this->genres,
            'access_budget' => $this->access_budget,
            'project_managers' => $this->managerUsers,
            'write_auth' => $this->writeUsers,
            'curr_user_is_related' => $this->users->contains(Auth::id()),
            'num_of_guests' => $this->num_of_guests,
            'entry_fee' => $this->entry_fee,
            'registration_required' => $this->registration_required,
            'register_by' => $this->register_by,
            'registration_deadline' => $this->registration_deadline,
            'is_closed_society' => $this->is_closed_society,

            'cost_center' => $this->cost_center,
            'copyright' => new CopyrightResource($this->copyright),
            'moneySources' => $this->money_sources,

            'users' => UserIndexResource::collection($this->users)->resolve(),
            'project_history' => $historyArray,
            'departments' => DepartmentIndexResource::collection($this->departments)->resolve(),

            'project_files' => ProjectFileResource::collection($this->project_files),
            'contracts' => ContractResource::collection($this->contracts),

            'isMemberOfADepartment' => $this->departments->contains(fn ($department) => $department->users->contains(Auth::user())),

            'public_checklists' => ChecklistIndexResource::collection($this->checklists->whereNull('user_id'))->resolve(),
            'private_checklists' => ChecklistIndexResource::collection($this->checklists->where('user_id', Auth::id()))->resolve(),

            'comments' => $this->comments->map(fn ($comment) => [
                'id' => $comment->id,
                'text' => $comment->text,
                'created_at' => $comment->created_at->format('d.m.Y, H:i'),
                'user' => $comment->user
            ])
        ];
    }
}
