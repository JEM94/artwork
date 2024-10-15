<?php

namespace Artwork\Modules\IndividualTimes\Services;

use Artwork\Modules\Freelancer\Models\Freelancer;
use Artwork\Modules\IndividualTimes\Models\IndividualTime;
use Artwork\Modules\IndividualTimes\Repositories\IndividualTimeRepository;
use Artwork\Modules\ServiceProvider\Models\ServiceProvider;
use Artwork\Modules\User\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use stdClass;

readonly class IndividualTimeService
{
    public function __construct(
        private readonly IndividualTimeRepository $individualTimeRepository,
    ) {
    }

    public function findById(int $id)
    {
        return $this->individualTimeRepository->find($id);
    }

    public function updateForModel(
        $modelInstance,
        $individualTime,
        string $title,
        ?string $startTime,
        ?string $endTime,
        string $date
    ): bool {
        $isFullDay = false;
        if (!method_exists($modelInstance, 'individualTimes')) {
            throw new ModelNotFoundException("Model does not support individual times");
        }

        if ($startTime && $endTime) {
            $startDateForConvert = Carbon::parse($date . ' ' . $startTime);

            [$startTimeConverted, $endTimeConverted] = $this->processTimes(
                $startDateForConvert,
                $startTime,
                $endTime,
                Carbon::parse($date)
            );
            $workingTimeInMinutes = $startTimeConverted->diffInMinutes($endTimeConverted);
        } else {
            $startTimeConverted = Carbon::parse($date);
            $endTimeConverted = Carbon::parse($date);
            $workingTimeInMinutes = 1440;
            $isFullDay = true;
        }

        return $individualTime->update([
            'title' => $title,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'start_date' => $startTimeConverted->format('Y-m-d'),
            'end_date' => $endTimeConverted->format('Y-m-d'),
            'full_day' => $isFullDay,
            'working_time_minutes' => $workingTimeInMinutes,
        ]);
    }

    public function createForModel(
        $modelInstance,
        ?string $title,
        ?string $startTime,
        ?string $endTime,
        string $date
    ): IndividualTime {
        $isFullDay = false;
        if (!method_exists($modelInstance, 'individualTimes')) {
            throw new ModelNotFoundException("Model does not support individual times");
        }

        if ($startTime && $endTime) {
            $startDateForConvert = Carbon::parse($date . ' ' . $startTime);

            [$startTimeConverted, $endTimeConverted] = $this->processTimes(
                $startDateForConvert,
                $startTime,
                $endTime,
                Carbon::parse($date)
            );
            $workingTimeInMinutes = $startTimeConverted->diffInMinutes($endTimeConverted);
        } else {
            $startTimeConverted = Carbon::parse($date);
            $endTimeConverted = Carbon::parse($date);
            $workingTimeInMinutes = 1440;
            $isFullDay = true;
        }

        $individualTimeObject = [
            'title' => $title,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'start_date' => $startTimeConverted->format('Y-m-d'),
            'end_date' => $endTimeConverted->format('Y-m-d'),
            'full_day' => $isFullDay,
            'working_time_minutes' => $workingTimeInMinutes,
        ];

        return $this->individualTimeRepository->createNewIndividualTime($modelInstance, $individualTimeObject);
    }

    /**
     * @param Carbon $startDate
     * @param string|null $startTime
     * @param string|null $endTime
     * @param Carbon|null $endDate
     * @return Carbon[]
     */
    private function processTimes(Carbon $startDate, ?string $startTime, ?string $endTime, ?Carbon $endDate): array
    {
        $endDay = clone $startDate;
        $startTime = Carbon::parse($startTime);
        $endTime = Carbon::parse($endTime);
        if ($endDate && !$endDate->isSameDay($startDate)) {
            $endDay = clone $endDate;
        } elseif ($endTime->lte($startTime)) {
            $endDay->addDay();
        }
        $startDate->setTimeFromTimeString($startTime->toTimeString());
        $endDay->setTimeFromTimeString($endTime->toTimeString());
        return [$startDate, $endDay];
    }
}
