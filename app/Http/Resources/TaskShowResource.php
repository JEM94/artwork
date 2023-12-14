<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskShowResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
    public function toArray($request): array
    {
        return [
            'resource' => class_basename($this),
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'done' => (bool) $this->done_at,
            'humanDeadline' => $this->deadline
                ?->setTimezone($request->get('timezone', config('calendar.default_timezone')))
                ->format('d.m.Y'),
            'deadline' => $this->deadline?->timestamp,
            'isDeadlineInFuture' => $this->deadline?->isFuture(),
            'isPrivate' => (bool) $this->checklist?->user_id,
            'projectId' => $this->checklist?->project->id,
            'projectName' => $this->checklist?->project->name,
            'users' => $this->checklist ? UserIconResource::collection($this->checklist->users) : null,
            'checklistName' => $this->checklist?->name,
            'checklistId' => $this->checklist?->id,
        ];
    }
}
