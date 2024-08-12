<?php

namespace Artwork\Modules\ChecklistTemplate\Repositories;

use Artwork\Core\Database\Repository\BaseRepository;
use Artwork\Modules\ChecklistTemplate\Models\ChecklistTemplate;
use Illuminate\Support\Collection;

class ChecklistTemplateRepository extends BaseRepository
{
    /**
     * @return array<string, mixed>
     */
    public function syncUsers(ChecklistTemplate $checklistTemplate, Collection $userIds): array
    {
        return $checklistTemplate->users()->sync($userIds);
    }
}
