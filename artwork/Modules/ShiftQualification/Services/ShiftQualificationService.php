<?php

namespace Artwork\Modules\ShiftQualification\Services;

use Artwork\Modules\ShiftQualification\Http\Requests\StoreShiftQualificationRequest;
use Artwork\Modules\ShiftQualification\Http\Requests\UpdateShiftQualificationRequest;
use Artwork\Modules\ShiftQualification\Models\ShiftQualification;
use Artwork\Modules\ShiftQualification\Repositories\ShiftQualificationRepository;
use Throwable;

class ShiftQualificationService
{
    public function __construct(private readonly ShiftQualificationRepository $shiftQualificationRepository)
    {
    }

    /**
     * @throws Throwable
     */
    public function createFromRequest(StoreShiftQualificationRequest $storeShiftQualificationRequest): void
    {
        $this->shiftQualificationRepository->saveOrFail(
            new ShiftQualification(
                $storeShiftQualificationRequest->only(['icon', 'name', 'available'])
            )
        );
    }

    /**
     * @throws Throwable
     */
    public function updateFromRequest(
        UpdateShiftQualificationRequest $updateShiftQualificationRequest,
        ShiftQualification $shiftQualification
    ): void {
        $this->shiftQualificationRepository->saveOrFail(
            $shiftQualification->fill($updateShiftQualificationRequest->only('icon', 'name', 'available'))
        );
    }
}
