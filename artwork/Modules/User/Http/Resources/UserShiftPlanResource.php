<?php

namespace Artwork\Modules\User\Http\Resources;

use Artwork\Modules\Permission\Enums\PermissionEnum;
use Artwork\Modules\Shift\Abstracts\WorkerShiftPlanResource;
use Artwork\Modules\User\Models\User;

/**
 * @mixin User
 */
class UserShiftPlanResource extends WorkerShiftPlanResource
{
    /**
     * @return array<string, mixed>
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
    public function toArray($request): array
    {
        return array_merge(
            [
                'first_name' => $this->getAttribute('first_name'),
                'last_name' => $this->getAttribute('last_name'),
                'profile_photo_url' => $this->getAttribute('profile_photo_url'),
                'email' => $this->getAttribute('email'),
                'departments' => $this->getAttribute('departments'),
                'position' => $this->getAttribute('position'),
                'business' => $this->getAttribute('business'),
                'phone_number' => $this->getAttribute('phone_number'),
                'project_management' => $this->can(PermissionEnum::PROJECT_MANAGEMENT->value),
                'display_name' => $this->getDisplayNameAttribute(),
            ],
            parent::toArray($request)
        );
    }
}
